<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminContractRequest;
use App\Models\Contract;
use App\Models\FinanceRequest;
use App\Models\RequestTimeline;
use App\Support\ContractDocumentBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'currentContract',
        ]);

        return response()->json([
            'request' => $financeRequest,
            'contract' => $financeRequest->currentContract,
        ]);
    }

    public function storeAndSend(StoreAdminContractRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $admin = $request->user();
        $terms = [
            'commission' => trim((string) $request->input('commission')),
            'interest' => trim((string) $request->input('interest')),
            'payment_period' => trim((string) $request->input('payment_period')),
            'general_terms' => array_values(array_filter(array_map(static fn ($value) => trim((string) $value), (array) $request->input('general_terms', [])))),
            'special_terms' => trim((string) $request->input('special_terms', '')),
        ];

        $contract = DB::transaction(function () use ($financeRequest, $admin, $request, $terms) {
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
                'version_no' => $versionNo,
                'generated_by' => $admin->id,
                'generated_at' => now(),
                'status' => ContractStatus::GENERATED,
                'is_current' => true,
                'terms_json' => $terms,
                'contract_content' => '',
            ]);

            $contract->admin_signature_path = $this->storeSignatureDataUrl(
                (string) $request->input('signature_data_url'),
                'contracts/signatures/admin/' . $financeRequest->id . '-v' . $versionNo . '-admin.png'
            );
            $contract->admin_signed_at = now();
            $contract->admin_signed_by = $admin->id;
            $contract->status = ContractStatus::ADMIN_SIGNED;

            $this->renderAndPersistContractAssets($financeRequest, $contract, $terms);
            $contract->save();

            $financeRequest->current_contract_id = $contract->id;
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::CONTRACT;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimeline::create([
                'finance_request_id' => $financeRequest->id,
                'actor_user_id' => $admin->id,
                'event_type' => 'contract.admin_signed',
                'event_title' => 'Contract drafted and signed by admin',
                'event_description' => 'The contract was prepared, signed by the admin, and sent to the client for final review and signature.',
                'metadata_json' => [
                    'contract_id' => $contract->id,
                    'version_no' => $contract->version_no,
                ],
                'created_at' => now(),
            ]);

            return $contract;
        });

        return response()->json([
            'message' => 'Contract saved and sent to the client.',
            'contract' => $contract->fresh(),
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract']),
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

    private function renderAndPersistContractAssets(FinanceRequest $financeRequest, Contract $contract, array $terms): void
    {
        $contractHtml = ContractDocumentBuilder::buildHtml(
            $financeRequest->fresh('client'),
            $terms,
            $contract
        );

        $contract->contract_content = $contractHtml;

        $pdfRelativePath = 'contracts/pdfs/request-' . $financeRequest->id . '-v' . $contract->version_no . '.pdf';
        $this->storePdf($contractHtml, $pdfRelativePath);
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
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setWarnings(false);

        Storage::disk('public')->put($relativePath, $pdf->output());
    }
}
