<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SignClientContractRequest;
use App\Models\Contract;
use App\Models\FinanceRequest;
use App\Support\ContractDocumentBuilder;
use App\Support\ContractTemplateResolver;
use App\Support\MpdfContractPdfRenderer;
use App\Support\RequestTimelineLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        abort_unless(
            ($financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage) === FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
            422,
            'This request is not currently waiting for client signature.'
        );

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

            $requiresCommercialRegistration = (bool) $contract->requires_commercial_registration;

            $financeRequest->workflow_stage = $requiresCommercialRegistration
                ? FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD
                : FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'contract.client_signed',
                $client->id,
                'Contract signed by client',
                'تم توقيع العقد من العميل',
                'The client reviewed the contract and submitted the final signature.',
                'قام العميل بمراجعة العقد وإرسال التوقيع النهائي.',
                [
                    'contract_id' => $contract->id,
                    'contract_status' => $contract->status->value,
                    'requires_commercial_registration' => $requiresCommercialRegistration,
                ],
            );
        });

        return response()->json([
            'message' => 'Contract signed successfully.',
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract.template']),
        ]);
    }

    public function uploadCommercialRegistration(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $this->authorizeClient($financeRequest);
        $contract = $financeRequest->currentContract;

        abort_unless($contract, 404, 'No contract is available for this request.');
        abort_unless((bool) $contract->requires_commercial_registration, 422, 'Commercial registration upload is not required for this contract.');
        abort_unless(
            ($financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage) === FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
            422,
            'This request is not currently waiting for a commercial registration upload.'
        );

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $client = $request->user();

        DB::transaction(function () use ($financeRequest, $contract, $file, $client) {
            $uploaded = $this->storeUploadedFile(
                $file,
                'contracts/commercial/client/' . $financeRequest->id
            );

            $contract->client_commercial_contract_name = $uploaded['name'];
            $contract->client_commercial_contract_path = $uploaded['path'];
            $contract->client_commercial_contract_mime_type = $uploaded['mime_type'];
            $contract->client_commercial_contract_size = $uploaded['size'];
            $contract->client_commercial_uploaded_at = now();
            $contract->save();

            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'contract.client_commercial_registration_uploaded',
                $client->id,
                'Client uploaded authenticated Ghurfat Tijar contract',
                'قام العميل برفع عقد غرفة تجار الموثق',
                'The client uploaded the authenticated Ghurfat Tijar contract for admin review.',
                'قام العميل برفع عقد غرفة تجار الموثق لمراجعته من الإدارة.',
                [
                    'contract_id' => $contract->id,
                ],
            );
        });

        return response()->json([
            'message' => 'Commercial registration contract uploaded successfully.',
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract.template']),
        ]);
    }

    public function downloadPdf(Request $request, FinanceRequest $financeRequest): StreamedResponse
    {
        $this->authorizeClient($financeRequest);

        $contract = $financeRequest->currentContract;
        abort_unless($contract, 404);

        $asset = $this->resolvePrimaryContractAsset($financeRequest, $contract);
        abort_unless($asset !== null, 404);

        return $this->downloadStoredFile(
            $asset['path'],
            $asset['name'],
            $asset['mime_type'],
            $request->boolean('preview')
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

    private function renderAndPersistFinalPdf(FinanceRequest $financeRequest, Contract $contract): void
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

        Storage::disk('public')->put($pdfRelativePath, MpdfContractPdfRenderer::renderToString($documentHtml));
        $contract->contract_pdf_path = $pdfRelativePath;
    }

    private function storeUploadedFile(UploadedFile $file, string $directory): array
    {
        $path = $file->store($directory, 'public');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    private function resolvePrimaryContractAsset(FinanceRequest $financeRequest, Contract $contract): ?array
    {
        if (filled($contract->admin_commercial_contract_path)) {
            return [
                'path' => $contract->admin_commercial_contract_path,
                'name' => $contract->admin_commercial_contract_name ?: ('admin-commercial-' . $financeRequest->reference_number),
                'mime_type' => $contract->admin_commercial_contract_mime_type,
            ];
        }

        if (filled($contract->client_commercial_contract_path)) {
            return [
                'path' => $contract->client_commercial_contract_path,
                'name' => $contract->client_commercial_contract_name ?: ('client-commercial-' . $financeRequest->reference_number),
                'mime_type' => $contract->client_commercial_contract_mime_type,
            ];
        }

        if (filled($contract->admin_uploaded_contract_path)) {
            return [
                'path' => $contract->admin_uploaded_contract_path,
                'name' => $contract->admin_uploaded_contract_name ?: ('contract-' . $financeRequest->reference_number),
                'mime_type' => $contract->admin_uploaded_contract_mime_type,
            ];
        }

        if (! filled($contract->contract_pdf_path)) {
            return null;
        }

        return [
            'path' => $contract->contract_pdf_path,
            'name' => 'contract-' . $financeRequest->reference_number . '.pdf',
            'mime_type' => 'application/pdf',
        ];
    }

    private function downloadStoredFile(string $path, string $filename, ?string $mimeType, bool $preview = false): StreamedResponse
    {
        abort_unless(Storage::disk('public')->exists($path), 404);

        $resolvedMimeType = $mimeType ?: Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        if ($preview && $this->isPreviewableMimeType($resolvedMimeType)) {
            return Storage::disk('public')->response(
                $path,
                $filename,
                [
                    'Content-Type' => $resolvedMimeType,
                    'X-Content-Type-Options' => 'nosniff',
                ],
                'inline'
            );
        }

        return Storage::disk('public')->download($path, $filename);
    }

    private function isPreviewableMimeType(string $mimeType): bool
    {
        if (str_starts_with($mimeType, 'image/')) {
            return true;
        }

        return in_array($mimeType, [
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/json',
        ], true);
    }
}
