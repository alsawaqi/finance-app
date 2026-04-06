<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminContractRequest;
use App\Models\Contract;
use App\Models\ContractTemplate;
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
        $this->ensureAdminRole($request);
        $admin = $request->user();
        $uploadedContract = $request->file('uploaded_contract_file');

        if ($uploadedContract instanceof UploadedFile) {
            $contract = DB::transaction(function () use ($financeRequest, $admin, $uploadedContract) {
                Contract::query()
                    ->where('finance_request_id', $financeRequest->id)
                    ->where('is_current', true)
                    ->update([
                        'is_current' => false,
                        'status' => ContractStatus::SUPERSEDED,
                    ]);

                $versionNo = (int) Contract::query()
                    ->where('finance_request_id', $financeRequest->id)
                    ->max('version_no') + 1;

                $uploaded = $this->storeUploadedFile(
                    $uploadedContract,
                    'contracts/admin-uploaded/' . $financeRequest->id
                );

                $contract = Contract::create([
                    'finance_request_id' => $financeRequest->id,
                    'contract_template_id' => null,
                    'version_no' => $versionNo,
                    'contract_content' => '',
                    'contract_pdf_path' => $uploaded['path'],
                    'generated_by' => $admin->id,
                    'generated_at' => now(),
                    'admin_signed_at' => now(),
                    'admin_signed_by' => $admin->id,
                    'admin_signature_path' => null,
                    'client_signed_at' => now(),
                    'client_signed_by' => null,
                    'client_signature_path' => null,
                    'status' => ContractStatus::FULLY_SIGNED,
                    'contract_source' => 'admin_uploaded_attachment',
                    'client_signature_skipped' => true,
                    'requires_commercial_registration' => false,
                    'admin_uploaded_contract_name' => $uploaded['name'],
                    'admin_uploaded_contract_path' => $uploaded['path'],
                    'admin_uploaded_contract_mime_type' => $uploaded['mime_type'],
                    'admin_uploaded_contract_size' => $uploaded['size'],
                    'admin_uploaded_contract_at' => now(),
                    'terms_json' => [
                        'source' => 'admin_uploaded_attachment',
                    ],
                    'is_current' => true,
                ]);

                $financeRequest->current_contract_id = $contract->id;
                $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT;
                $financeRequest->latest_activity_at = now();
                $financeRequest->save();

                RequestTimelineLogger::log(
                    $financeRequest,
                    'contract.admin_uploaded_and_auto_completed',
                    $admin->id,
                    'Admin uploaded signed contract and skipped client signature',
                    'قام المسؤول برفع عقد موقع وتجاوز توقيع العميل',
                    'The admin uploaded an existing signed contract. Client signature was skipped and the request moved to staff assignment.',
                    'قام المسؤول برفع عقد موقع مسبقا. تم تجاوز توقيع العميل ونقل الطلب مباشرة إلى مرحلة تعيين الموظف.',
                    [
                        'contract_id' => $contract->id,
                        'version_no' => $contract->version_no,
                        'contract_source' => $contract->contract_source,
                    ],
                );

                return $contract;
            });

            return response()->json([
                'message' => 'Contract attachment uploaded. The request is now ready for staff assignment.',
                'contract' => $contract->fresh(['template']),
                'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract.template']),
            ]);
        }

        $template = ContractTemplate::query()
            ->where('slug', (string) $request->input('contract_template_slug'))
            ->where('is_active', true)
            ->orderByDesc('version_no')
            ->first()
            ?: ContractTemplateResolver::resolveTemplateForRequest($financeRequest);

        $contractBodyHtml = trim((string) $request->input('contract_body_html'));
        abort_if($contractBodyHtml === '', 422, 'Contract body is required.');

        $requiresCommercialRegistration = $request->boolean('requires_commercial_registration');

        $contract = DB::transaction(function () use (
            $financeRequest,
            $admin,
            $request,
            $template,
            $contractBodyHtml,
            $requiresCommercialRegistration
        ) {
            Contract::query()
                ->where('finance_request_id', $financeRequest->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'status' => ContractStatus::SUPERSEDED,
                ]);

            $versionNo = (int) Contract::query()
                ->where('finance_request_id', $financeRequest->id)
                ->max('version_no') + 1;

            $contract = Contract::create([
                'finance_request_id' => $financeRequest->id,
                'contract_template_id' => $template->id,
                'version_no' => $versionNo,
                'generated_by' => $admin->id,
                'generated_at' => now(),
                'status' => ContractStatus::GENERATED,
                'contract_source' => 'generated',
                'client_signature_skipped' => false,
                'requires_commercial_registration' => $requiresCommercialRegistration,
                'is_current' => true,
                'terms_json' => [
                    'template_slug' => $template->slug,
                    'template_name' => $template->name,
                    'requires_commercial_registration' => $requiresCommercialRegistration,
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
                    'requires_commercial_registration' => $requiresCommercialRegistration,
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

    public function uploadAdminCommercialRegistration(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $this->ensureAdminRole($request);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $admin = $request->user();
        $contract = $financeRequest->currentContract;
        $workflowStage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;

        abort_unless($contract, 404, 'No contract is available for this request.');
        abort_unless((bool) $contract->requires_commercial_registration, 422, 'Commercial registration upload is not required for this contract.');
        abort_unless(filled($contract->client_commercial_contract_path), 422, 'Client must upload the commercial registration contract first.');
        abort_unless(
            in_array($workflowStage, [
                FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
            ], true),
            422,
            'This request is not ready for admin commercial registration upload.'
        );

        $isReplacementUpload = filled($contract->admin_commercial_contract_path);

        DB::transaction(function () use ($financeRequest, $contract, $file, $admin, $isReplacementUpload) {
            $uploaded = $this->storeUploadedFile(
                $file,
                'contracts/commercial/admin/' . $financeRequest->id
            );

            $this->deleteStoredFileIfExists($contract->admin_commercial_contract_path);

            $contract->admin_commercial_contract_name = $uploaded['name'];
            $contract->admin_commercial_contract_path = $uploaded['path'];
            $contract->admin_commercial_contract_mime_type = $uploaded['mime_type'];
            $contract->admin_commercial_contract_size = $uploaded['size'];
            $contract->admin_commercial_uploaded_at = now();
            $contract->save();

            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'contract.admin_commercial_registration_uploaded',
                $admin?->id,
                $isReplacementUpload
                    ? 'Admin replaced authenticated Ghurfat Tijar contract upload'
                    : 'Admin uploaded authenticated Ghurfat Tijar contract',
                $isReplacementUpload
                    ? 'قام المسؤول باستبدال ملف عقد غرفة تجار الموثق'
                    : 'قام المسؤول برفع عقد غرفة تجار الموثق',
                $isReplacementUpload
                    ? 'The admin replaced the authenticated Ghurfat Tijar contract file and kept the request ready for staff assignment.'
                    : 'The admin uploaded the authenticated Ghurfat Tijar contract and moved the request to staff assignment.',
                $isReplacementUpload
                    ? 'قام المسؤول باستبدال ملف عقد غرفة تجار الموثق مع بقاء الطلب جاهزاً لتعيين الموظف.'
                    : 'قام المسؤول برفع عقد غرفة تجار الموثق وتم نقل الطلب إلى مرحلة تعيين الموظف.',
                [
                    'contract_id' => $contract->id,
                    'replaced_existing_upload' => $isReplacementUpload,
                ],
            );
        });

        return response()->json([
            'message' => 'Commercial registration contract uploaded. The request is now ready for staff assignment.',
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract.template']),
        ]);
    }

    public function requestClientCommercialRegistrationReupload(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $this->ensureAdminRole($request);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $admin = $request->user();
        $contract = $financeRequest->currentContract;
        $workflowStage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;

        abort_unless($contract, 404, 'No contract is available for this request.');
        abort_unless((bool) $contract->requires_commercial_registration, 422, 'Commercial registration upload is not required for this contract.');
        abort_unless(filled($contract->client_commercial_contract_path), 422, 'The client has not uploaded an authenticated commercial registration contract yet.');
        abort_unless(
            in_array($workflowStage, [
                FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
            ], true),
            422,
            'This request is not in a state where client commercial registration re-upload can be requested.'
        );

        $reason = trim((string) ($validated['reason'] ?? ''));

        DB::transaction(function () use ($financeRequest, $contract, $admin, $reason) {
            $clientPath = $contract->client_commercial_contract_path;
            $adminPath = $contract->admin_commercial_contract_path;

            $contract->client_commercial_contract_name = null;
            $contract->client_commercial_contract_path = null;
            $contract->client_commercial_contract_mime_type = null;
            $contract->client_commercial_contract_size = null;
            $contract->client_commercial_uploaded_at = null;

            $contract->admin_commercial_contract_name = null;
            $contract->admin_commercial_contract_path = null;
            $contract->admin_commercial_contract_mime_type = null;
            $contract->admin_commercial_contract_size = null;
            $contract->admin_commercial_uploaded_at = null;
            $contract->save();

            $this->deleteStoredFileIfExists($clientPath);
            $this->deleteStoredFileIfExists($adminPath);

            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'contract.client_commercial_registration_reupload_requested',
                $admin?->id,
                'Admin requested client re-upload for authenticated Ghurfat Tijar contract',
                'طلب المسؤول من العميل إعادة رفع عقد غرفة تجار الموثق',
                $reason !== ''
                    ? 'The admin requested the client to re-upload the authenticated Ghurfat Tijar contract. Reason: ' . $reason
                    : 'The admin requested the client to re-upload the authenticated Ghurfat Tijar contract.',
                $reason !== ''
                    ? 'طلب المسؤول من العميل إعادة رفع عقد غرفة تجار الموثق. السبب: ' . $reason
                    : 'طلب المسؤول من العميل إعادة رفع عقد غرفة تجار الموثق.',
                [
                    'contract_id' => $contract->id,
                    'reason' => $reason !== '' ? $reason : null,
                ],
            );
        });

        return response()->json([
            'message' => 'Client re-upload was requested. The request is now waiting for client authenticated contract upload.',
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract.template']),
        ]);
    }

    public function downloadPdf(Request $request, FinanceRequest $financeRequest): StreamedResponse
    {
        $this->ensureAdminRole($request);
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

    public function downloadCommercialRegistration(Request $request, FinanceRequest $financeRequest, string $party): StreamedResponse
    {
        $this->ensureAdminRole($request);
        $contract = $financeRequest->currentContract;
        abort_unless($contract, 404);

        $partyKey = strtolower(trim($party));
        abort_unless(in_array($partyKey, ['client', 'admin'], true), 404);

        if ($partyKey === 'client') {
            abort_unless(filled($contract->client_commercial_contract_path), 404);

            return $this->downloadStoredFile(
                $contract->client_commercial_contract_path,
                $contract->client_commercial_contract_name ?: ('client-commercial-' . $financeRequest->reference_number),
                $contract->client_commercial_contract_mime_type,
                $request->boolean('preview')
            );
        }

        abort_unless(filled($contract->admin_commercial_contract_path), 404);

        return $this->downloadStoredFile(
            $contract->admin_commercial_contract_path,
            $contract->admin_commercial_contract_name ?: ('admin-commercial-' . $financeRequest->reference_number),
            $contract->admin_commercial_contract_mime_type,
            $request->boolean('preview')
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

    private function deleteStoredFileIfExists(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
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

    private function ensureAdminRole(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('admin'), 403);
    }
}
