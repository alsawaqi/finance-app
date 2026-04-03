<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminContractRequest;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\FinanceRequest;
use App\Support\RequestTimelineLogger;
use App\Support\ContractDocumentBuilder;
use App\Support\ContractTemplateResolver;
use App\Support\MpdfContractPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminContractController extends Controller
{
    public function show(FinanceRequest $financeRequest): JsonResponse
    {
        $financeRequest->load([
            'client:id,name,email,phone',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments',
            'shareholders',
            'currentContract.template',
        ]);

        $template = $financeRequest->currentContract?->template
            ?: ContractTemplateResolver::resolveTemplateForRequest($financeRequest);

        $draftContractHtml = trim((string) ($financeRequest->currentContract?->contract_content ?? ''));
        if ($draftContractHtml === '') {
            $draftContractHtml = ContractTemplateResolver::renderEditableHtml($financeRequest, $template);
        }

        return response()->json([
            'request' => $financeRequest,
            'contract' => $financeRequest->currentContract,
            'contract_template' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'version_no' => $template->version_no,
            ],
            'draft_contract_html' => $draftContractHtml,
        ]);
    }

    public function storeAndSend(StoreAdminContractRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $admin = $request->user();

        $template = ContractTemplate::query()
            ->where('slug', (string) $request->input('contract_template_slug'))
            ->where('is_active', true)
            ->orderByDesc('version_no')
            ->first()
            ?: ContractTemplateResolver::resolveTemplateForRequest($financeRequest);

        $contractBodyHtml = trim((string) $request->input('contract_body_html'));
        abort_if($contractBodyHtml === '', 422, 'Contract body is required.');

        $contract = DB::transaction(function () use ($financeRequest, $admin, $request, $template, $contractBodyHtml) {
            Contract::query()
                ->where('finance_request_id', $financeRequest->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'status' => ContractStatus::SUPERSEDED,
                ]);

            $versionNo = (int) Contract::query()->where('finance_request_id', $financeRequest->id)->max('version_no') + 1;

            $contract = Contract::create([
                'finance_request_id' => $financeRequest->id,
                'contract_template_id' => $template->id,
                'version_no' => $versionNo,
                'generated_by' => $admin->id,
                'generated_at' => now(),
                'status' => ContractStatus::GENERATED,
                'is_current' => true,
                'terms_json' => [
                    'template_slug' => $template->slug,
                    'template_name' => $template->name,
                ],
                'contract_content' => $contractBodyHtml,
            ]);

            $contract->admin_signature_path = $this->storeSignatureDataUrl(
                (string) $request->input('signature_data_url'),
                'contracts/signatures/admin/' . $financeRequest->id . '-v' . $versionNo . '-admin.png'
            );
            $contract->admin_signed_at = now();
            $contract->admin_signed_by = $admin->id;
            $contract->status = ContractStatus::ADMIN_SIGNED;

            $this->renderAndPersistContractAssets($financeRequest, $contract);
            $contract->save();

            $financeRequest->current_contract_id = $contract->id;
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'contract.admin_signed',
                $admin->id,
                'Contract drafted and signed by admin',
                'تم إعداد العقد وتوقيعه من الإدارة',
                'The contract was prepared from the selected Arabic template, signed by the admin, and sent to the client for final review and signature.',
                'تم إعداد العقد من القالب العربي المحدد وتوقيعه من الإدارة وإرساله إلى العميل للمراجعة النهائية والتوقيع.',
                [
                    'contract_id' => $contract->id,
                    'version_no' => $contract->version_no,
                    'template_slug' => $template->slug,
                ],
            );

            return $contract;
        });

        return response()->json([
            'message' => 'Contract saved and sent to the client.',
            'contract' => $contract->fresh(['template']),
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract.template']),
        ]);
    }

    public function downloadPdf(FinanceRequest $financeRequest)
    {
        $contract = $financeRequest->currentContract;

        abort_unless($contract && $contract->contract_pdf_path && Storage::disk('public')->exists($contract->contract_pdf_path), 404);

        return Storage::disk('public')->download(
            $contract->contract_pdf_path,
            'contract-' . $financeRequest->reference_number . '.pdf'
        );
    }

    private function renderAndPersistContractAssets(FinanceRequest $financeRequest, Contract $contract): void
    {
        $bodyHtml = trim((string) $contract->contract_content);

        if ($bodyHtml === '') {
            $template = $contract->template ?: ContractTemplateResolver::resolveTemplateForRequest($financeRequest);
            $bodyHtml = ContractTemplateResolver::renderEditableHtml($financeRequest->fresh('client'), $template);
            $contract->contract_content = $bodyHtml;
        }

        $documentHtml = ContractDocumentBuilder::buildPdfHtml(
            $financeRequest->fresh('client'),
            $bodyHtml,
            $contract
        );

        $pdfRelativePath = 'contracts/pdfs/request-' . $financeRequest->id . '-v' . $contract->version_no . '.pdf';
        $this->storePdf($documentHtml, $pdfRelativePath);
        $contract->contract_pdf_path = $pdfRelativePath;
    }

    private function storeSignatureDataUrl(string $dataUrl, string $relativePath): string
    {
        if (! str_starts_with($dataUrl, 'data:image/')) {
            abort(422, 'Invalid signature payload.');
        }

        [, $encoded] = explode(',', $dataUrl, 2);
        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            abort(422, 'Invalid signature payload.');
        }

        Storage::disk('public')->put($relativePath, $binary);

        return $relativePath;
    }

    private function storePdf(string $html, string $relativePath): void
    {
        $binaryPdf = MpdfContractPdfRenderer::renderToString($html);
        Storage::disk('public')->put($relativePath, $binaryPdf);
    }
}