<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SignClientContractRequest;
use App\Models\FinanceRequest;
use App\Support\RequestTimelineLogger;
use App\Support\ContractDocumentBuilder;
use App\Support\ContractTemplateResolver;
use App\Support\MpdfContractPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientContractController extends Controller
{
    public function show(FinanceRequest $financeRequest): JsonResponse
    {
        $this->authorizeClient($financeRequest);

        $financeRequest->load([
            'client:id,name,email,phone',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments',
            'timeline.actor:id,name',
            'currentContract.template',
            'shareholders',
        ]);

        return response()->json([
            'request' => $financeRequest,
            'contract' => $financeRequest->currentContract,
        ]);
    }

    public function sign(SignClientContractRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $this->authorizeClient($financeRequest);
        $client = $request->user();

        $contract = $financeRequest->currentContract;
        abort_unless($contract && $contract->admin_signed_at, 404, 'No contract is ready for client signature.');

        DB::transaction(function () use ($request, $financeRequest, $client, $contract) {
            $signaturePath = $this->storeSignatureDataUrl(
                (string) $request->input('signature_data_url'),
                'contracts/signatures/client/' . $financeRequest->id . '-contract-' . $contract->id . '-client.png'
            );

            $contract->client_signature_path = $signaturePath;
            $contract->client_signed_at = now();
            $contract->client_signed_by = $client->id;
            $contract->status = ContractStatus::FULLY_SIGNED;

            $this->renderAndPersistFinalPdf($financeRequest, $contract);
            $contract->save();

            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'contract.client_signed',
                $client->id,
                'Contract signed by client',
                'تم توقيع العقد من العميل',
                'The client reviewed the Arabic contract template preview and submitted the final signature.',
                'قام العميل بمراجعة معاينة العقد العربي وإرسال التوقيع النهائي.',
                [
                    'contract_id' => $contract->id,
                    'contract_status' => $contract->status->value,
                ],
            );
        });

        return response()->json([
            'message' => 'Contract signed successfully.',
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract.template']),
        ]);
    }

    public function downloadPdf(FinanceRequest $financeRequest)
    {
        $this->authorizeClient($financeRequest);

        $contract = $financeRequest->currentContract;
        abort_unless($contract && $contract->contract_pdf_path && Storage::disk('public')->exists($contract->contract_pdf_path), 404);

        return Storage::disk('public')->download(
            $contract->contract_pdf_path,
            'contract-' . $financeRequest->reference_number . '.pdf'
        );
    }

    private function authorizeClient(FinanceRequest $financeRequest): void
    {
        abort_unless((int) $financeRequest->user_id === (int) auth()->id(), 403);
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

    private function renderAndPersistFinalPdf(FinanceRequest $financeRequest, $contract): void
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

        $pdfRelativePath = $contract->contract_pdf_path
            ?: 'contracts/pdfs/request-' . $financeRequest->id . '-v' . $contract->version_no . '.pdf';

        $binaryPdf = MpdfContractPdfRenderer::renderToString($documentHtml);
        Storage::disk('public')->put($pdfRelativePath, $binaryPdf);

        $contract->contract_pdf_path = $pdfRelativePath;
    }
}