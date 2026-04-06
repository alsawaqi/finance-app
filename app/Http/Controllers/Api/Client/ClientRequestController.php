<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\FinanceRequestPriority;
use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\FinanceRequestUpdateBatchStatus;
use App\Enums\FinanceRequestUpdateItemStatus;
use App\Enums\RequestAdditionalDocumentStatus;
use App\Enums\RequestCommentVisibility;
use App\Enums\RequestDocumentUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientFinanceRequestRequest;
use App\Http\Requests\Client\UploadAdditionalDocumentRequest;
use App\Http\Requests\Client\UploadRequiredDocumentRequest;
use App\Models\DocumentUploadStep;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestType;
use App\Models\RequestDocumentUpload;
use App\Models\FinanceRequestShareholder;
use App\Models\RequestAdditionalDocument;
use App\Models\RequestAnswer;
use App\Models\RequestAttachment;
use App\Models\RequestQuestion;
use App\Support\RequestTimelineLogger;
use App\Services\FinanceRequestDocumentChecklistService;
use App\Services\FinanceRequestWorkflowService;
use App\Services\FinanceRequestUpdateService;
use App\Services\Twilio\ClientRequestSubmittedWhatsAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
 
use Illuminate\Support\Str;

class ClientRequestController extends Controller
{
    public function __construct(
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
        private readonly FinanceRequestWorkflowService $workflowService,
        private readonly FinanceRequestUpdateService $updateService,
    ) {
    }

    public function questions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'finance_type' => ['nullable', 'string', 'in:individual,company'],
        ]);

        $financeType = $validated['finance_type'] ?? null;

        $questionsQuery = RequestQuestion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($financeType) {
            $questionsQuery->where(function ($query) use ($financeType): void {
                $query
                    ->where('finance_type', 'all')
                    ->orWhere('finance_type', $financeType);
            });
        }

        $questions = $questionsQuery
            ->get()
            ->map(fn (RequestQuestion $question) => [
                'id' => $question->id,
                'code' => $question->code,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'finance_type' => $question->finance_type ?? 'all',
                'options_json' => $question->options_json ?? [],
                'placeholder' => $question->placeholder,
                'help_text' => $question->help_text,
                'validation_rules' => $question->validation_rules,
                'is_required' => (bool) $question->is_required,
                'sort_order' => $question->sort_order,
            ])
            ->values();

        return response()->json([
            'questions' => $questions,
            'finance_request_types' => $this->serializeActiveFinanceRequestTypes(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);

        $requestsPaginator = FinanceRequest::query()
            ->with([
                'currentContract:id,finance_request_id,version_no,status,admin_signed_at,client_signed_at,contract_pdf_path',
                'financeRequestType:id,slug,name_en,name_ar,description_en,description_ar,is_active,sort_order',
                'staffQuestions.template:id,code,question_text_en,question_text_ar,question_type,is_required,is_active,sort_order',
            ])
            ->withCount(['answers', 'attachments'])
            ->where('user_id', $request->user()->id)
            ->latest('submitted_at')
            ->latest('id')
            ->paginate($perPage);

        $requests = collect($requestsPaginator->items())
            ->map(fn (FinanceRequest $financeRequest) => $this->transformRequestSummary($financeRequest))
            ->values();

        return response()->json([
            'requests' => $requests,
            'pagination' => $this->paginationMeta($requestsPaginator),
        ]);
    }

    public function store(StoreClientFinanceRequestRequest $request, ClientRequestSubmittedWhatsAppNotifier $requestSubmittedWhatsApp): JsonResponse
    {
    $user = $request->user();
    $validated = $request->validated();
    $details = Arr::get($validated, 'details', []);
    $answers = collect(Arr::get($validated, 'answers', []));
    $shareholders = array_values((array) Arr::get($validated, 'shareholders', []));
    $now = now();

    $financeRequest = DB::transaction(function () use ($request, $user, $details, $answers, $shareholders, $now) {
        $applicantType = (string) Arr::get($details, 'finance_type', 'individual');
        $applicantName = $this->resolveApplicantName($user);
        $countryCode = strtoupper(trim((string) Arr::get($details, 'country', '')));
        $requestedAmount = (float) Arr::get($details, 'requested_amount', 0);
        $companyName = trim((string) Arr::get($details, 'company_name', '')) ?: null;
        $email = trim((string) Arr::get($details, 'email', ''));
        $phoneCountryCode = trim((string) Arr::get($details, 'phone_country_code', ''));
        $phoneNumber = trim((string) Arr::get($details, 'phone_number', ''));
        $unifiedNumber = trim((string) Arr::get($details, 'unified_number', ''));
        $nationalAddressNumber = trim((string) Arr::get($details, 'national_address_number', ''));
        $companyCrNumber = trim((string) Arr::get($details, 'company_cr_number', ''));
        $address = trim((string) Arr::get($details, 'address', ''));
        $notes = Arr::get($details, 'notes');
        $financeRequestTypeId = Arr::get($details, 'finance_request_type_id');

        $financeRequest = FinanceRequest::create([
            'reference_number' => 'TMP-' . Str::upper(Str::random(12)),
            'user_id' => $user->id,
            'finance_request_type_id' => $financeRequestTypeId ? (int) $financeRequestTypeId : null,
            'applicant_type' => $applicantType,
            'company_name' => $companyName,
            'country_code' => $countryCode ?: null,
            'status' => FinanceRequestStatus::SUBMITTED,
            'workflow_stage' => FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW,
            'priority' => FinanceRequestPriority::NORMAL,
            'submitted_at' => $now,
            'latest_activity_at' => $now,
            'intake_details_json' => [
                    'applicant_name' => $applicantName,
                    'full_name' => $applicantName,
                    'name' => $applicantName,
                    'requested_amount' => $requestedAmount,
                    'finance_type' => $applicantType,
                    'finance_request_type_id' => $financeRequestTypeId ? (int) $financeRequestTypeId : null,
                    'company_name' => $companyName,
                    'email' => $email,
                    'phone_country_code' => $phoneCountryCode,
                    'phone_number' => $phoneNumber,
                    'unified_number' => $unifiedNumber,
                    'national_address_number' => $nationalAddressNumber,
                    'company_cr_number' => $companyCrNumber,
                    'address' => $address,
                    'notes' => $notes,
                ],
        ]);

        $financeRequest->forceFill([
            'reference_number' => $this->buildReferenceNumber($financeRequest),
        ])->save();

        foreach ($answers as $answer) {
            $questionId = (int) Arr::get($answer, 'question_id');
            $value = Arr::get($answer, 'value');

            [$answerText, $answerValueJson] = $this->normalizeAnswerPayload($value);

            RequestAnswer::create([
                'finance_request_id' => $financeRequest->id,
                'question_id' => $questionId,
                'answer_text' => $answerText,
                'answer_value_json' => $answerValueJson,
                'answered_by' => $user->id,
            ]);
        }

        $storedAttachments = [];

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('request-attachments/initial-submission', 'public');

            $storedAttachments[] = RequestAttachment::create([
                'finance_request_id' => $financeRequest->id,
                'category' => 'initial_submission',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);
        }

        if ($request->hasFile('national_address_attachment')) {
            $nationalAddressFile = $request->file('national_address_attachment');
            $path = $nationalAddressFile->store('request-attachments/national-address', 'public');

            $storedAttachments[] = RequestAttachment::create([
                'finance_request_id' => $financeRequest->id,
                'category' => 'national_address',
                'file_name' => $nationalAddressFile->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $nationalAddressFile->getClientMimeType(),
                'file_extension' => $nationalAddressFile->getClientOriginalExtension(),
                'file_size' => $nationalAddressFile->getSize(),
                'uploaded_by' => $user->id,
            ]);
        }

        if ($applicantType === 'company' && $request->hasFile('company_cr')) {
            $companyCr = $request->file('company_cr');
            $path = $companyCr->store('request-attachments/company-cr', 'public');

            $storedAttachments[] = RequestAttachment::create([
                'finance_request_id' => $financeRequest->id,
                'category' => 'company_cr',
                'file_name' => $companyCr->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $companyCr->getClientMimeType(),
                'file_extension' => $companyCr->getClientOriginalExtension(),
                'file_size' => $companyCr->getSize(),
                'uploaded_by' => $user->id,
            ]);
        }

        $storedShareholders = [];
        foreach ($shareholders as $index => $shareholder) {
            if (! $request->hasFile("shareholders.$index.id_file")) {
                continue;
            }

            $idFile = $request->file("shareholders.$index.id_file");
            $path = $idFile->store('request-shareholders', 'public');

            $storedShareholders[] = FinanceRequestShareholder::create([
                'finance_request_id' => $financeRequest->id,
                'shareholder_name' => trim((string) ($shareholder['name'] ?? '')),
                'phone_country_code' => trim((string) ($shareholder['phone_country_code'] ?? '')),
                'phone_number' => trim((string) ($shareholder['phone_number'] ?? '')),
                'id_number' => trim((string) ($shareholder['id_number'] ?? '')),
                'id_file_name' => $idFile->getClientOriginalName(),
                'id_file_path' => $path,
                'disk' => 'public',
                'mime_type' => $idFile->getClientMimeType(),
                'file_extension' => $idFile->getClientOriginalExtension(),
                'file_size' => $idFile->getSize(),
                'sort_order' => $index + 1,
            ]);
        }

        RequestTimelineLogger::log(
            $financeRequest,
            'request_submitted',
            $user->id,
            'Request submitted',
            'تم إرسال الطلب',
            'Client submitted a new finance request and it is now waiting for admin review.',
            'قام العميل بإرسال طلب تمويل جديد وهو الآن بانتظار مراجعة الإدارة.',
            [
                'reference_number' => $financeRequest->reference_number,
                'answer_count' => $answers->count(),
                'attachment_count' => count($storedAttachments),
                'shareholder_count' => count($storedShareholders),
                'applicant_type' => $applicantType,
                'requested_amount' => $requestedAmount,
                'country_code' => $countryCode ?: null,
                'company_name' => $companyName,
                'email' => $email,
                'phone_country_code' => $phoneCountryCode,
                'phone_number' => $phoneNumber,
                'unified_number' => $unifiedNumber,
                'national_address_number' => $nationalAddressNumber,
                'company_cr_number' => $companyCrNumber,
                'address' => $address,
                'finance_request_type_id' => $financeRequestTypeId ? (int) $financeRequestTypeId : null,
            ],
            $now,
        );

        return $financeRequest->fresh(['answers.question', 'attachments', 'shareholders', 'additionalDocuments', 'currentContract', 'financeRequestType']);
    });

        $requestSubmittedWhatsApp->notify(
            $user,
            (string) $financeRequest->reference_number,
            Arr::get($validated, 'details', []),
            $request->header('Accept-Language'),
        );

        return response()->json([
            'message' => 'Request submitted successfully.',
            'request' => $this->transformRequestDetails($financeRequest),
        ], 201);
    }

    public function show(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        abort_unless((int) $financeRequest->user_id === (int) $request->user()->id, 404);

        $financeRequest->load([
            'answers.question',
            'attachments',
            'client',
            'currentContract',
            'financeRequestType',
            'shareholders',
            'additionalDocuments',
            'comments' => fn ($query) => $query
                ->where('visibility', RequestCommentVisibility::CLIENT_VISIBLE->value)
                ->with('user:id,name,email')
                ->latest('created_at'),
            'updateBatches.requester:id,name,email',
            'updateBatches.items.question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required',
        ]);

        return response()->json([
            'request' => $this->transformRequestDetails($financeRequest),
        ]);
    }

    public function uploadRequiredDocument(UploadRequiredDocumentRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $this->authorizeClient($financeRequest, $request->user()->id);

        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;
        abort_unless(in_array($stage, [
            FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
            FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
            FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
        ], true), 422, 'This request is not currently accepting document uploads.');

        $step = DocumentUploadStep::query()
            ->whereKey((int) $request->integer('document_upload_step_id'))
            ->where('is_active', true)
            ->where('is_required', true)
            ->firstOrFail();

        $latestUpload = RequestDocumentUpload::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('document_upload_step_id', $step->id)
            ->latest('id')
            ->first();

        $latestStatus = $latestUpload?->status?->value ?? (string) ($latestUpload?->status ?? 'pending');

        if (! $step->is_multiple && $latestUpload && $latestStatus !== RequestDocumentUploadStatus::REJECTED->value) {
            abort(422, 'This required document is already locked after upload. A new version can only be uploaded after staff requests a change.');
        }

        $file = $request->file('file');

        DB::transaction(function () use ($request, $financeRequest, $step, $file, $latestUpload) {
            $path = $file->store('request-documents/required', 'public');

            RequestDocumentUpload::create([
                'finance_request_id' => $financeRequest->id,
                'document_upload_step_id' => $step->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'status' => RequestDocumentUploadStatus::UPLOADED,
                'uploaded_by' => $request->user()->id,
                'uploaded_at' => now(),
            ]);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.required_document_uploaded',
                $request->user()->id,
                'Required document uploaded',
                'تم رفع المستند المطلوب',
                'Client uploaded the required document: ' . $step->name . '.',
                'قام العميل برفع المستند المطلوب: ' . $step->name . '.',
                [
                    'document_upload_step_id' => $step->id,
                    'document_name' => $step->name,
                    'is_multiple_step' => (bool) $step->is_multiple,
                    'is_resubmission' => $latestUpload !== null,
                    'previous_upload_id' => $latestUpload?->id,
                ],
            );

            $this->workflowService->syncAfterRequiredDocuments($financeRequest->fresh());
        });

        return response()->json([
            'message' => 'Required document uploaded successfully.',
            'request' => $this->transformRequestDetails(
                $financeRequest->fresh(['answers.question', 'attachments', 'client', 'currentContract', 'financeRequestType', 'shareholders', 'additionalDocuments'])
            ),
        ]);
    }

    public function uploadAdditionalDocument(
        UploadAdditionalDocumentRequest $request,
        FinanceRequest $financeRequest,
        RequestAdditionalDocument $additionalDocument,
    ): JsonResponse {
        $this->authorizeClient($financeRequest, $request->user()->id);
        abort_unless((int) $additionalDocument->finance_request_id === (int) $financeRequest->id, 404);

        if (! in_array($additionalDocument->status?->value ?? (string) $additionalDocument->status, [
            RequestAdditionalDocumentStatus::PENDING->value,
            RequestAdditionalDocumentStatus::REJECTED->value,
        ], true)) {
            abort(422, 'This additional document is already locked after upload. A new version can only be uploaded if the team asks for another file.');
        }

        $file = $request->file('file');

        DB::transaction(function () use ($request, $financeRequest, $additionalDocument, $file) {
            $path = $file->store('request-documents/additional', 'public');

            $additionalDocument->update([
                'status' => RequestAdditionalDocumentStatus::UPLOADED,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
                'uploaded_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ]);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.additional_document_uploaded',
                $request->user()->id,
                'Additional document uploaded',
                'تم رفع المستند الإضافي',
                'Client uploaded the requested additional document: ' . $additionalDocument->title . '.',
                'قام العميل برفع المستند الإضافي المطلوب: ' . $additionalDocument->title . '.',
                [
                    'additional_document_id' => $additionalDocument->id,
                    'title' => $additionalDocument->title,
                ],
            );

            $this->workflowService->syncAfterAdditionalDocuments($financeRequest->fresh());
        });

        return response()->json([
            'message' => 'Additional document uploaded successfully.',
            'request' => $this->transformRequestDetails(
                $financeRequest->fresh(['answers.question', 'attachments', 'client', 'currentContract', 'financeRequestType', 'shareholders', 'additionalDocuments'])
            ),
        ]);
    }

    private function authorizeClient(FinanceRequest $financeRequest, int $userId): void
    {
        abort_unless((int) $financeRequest->user_id === $userId, 404);
    }

    private function buildReferenceNumber(FinanceRequest $financeRequest): string
    {
        return sprintf(
            'REQ-%s-%04d',
            Carbon::parse($financeRequest->created_at)->format('Ymd'),
            $financeRequest->id
        );
    }

    private function resolveApplicantName($user): string
{
    $firstName = trim((string) ($user->first_name ?? ''));
    $lastName = trim((string) ($user->last_name ?? ''));
    $fullName = trim($firstName . ' ' . $lastName);

    return $fullName !== '' ? $fullName : trim((string) ($user->name ?? ''));
}

    private function normalizeAnswerPayload(mixed $value): array
    {
        if (is_array($value)) {
            $clean = array_values(array_filter(array_map(function ($item) {
                if (is_scalar($item) || $item === null) {
                    return trim((string) $item);
                }

                return null;
            }, $value), fn ($item) => $item !== ''));

            return [
                implode(', ', $clean),
                $clean,
            ];
        }

        if (is_bool($value)) {
            return [$value ? 'Yes' : 'No', $value];
        }

        if ($value === null) {
            return [null, null];
        }

        return [trim((string) $value), $value];
    }

    private function transformRequestSummary(FinanceRequest $financeRequest): array
    {
        $details = $financeRequest->intake_details_json ?? [];
        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;
        $status = $financeRequest->status?->value ?? (string) $financeRequest->status;

        return [
            'id' => $financeRequest->id,
            'reference_number' => $financeRequest->reference_number,
            'approval_reference_number' => $financeRequest->approval_reference_number,
            'status' => $status,
            'workflow_stage' => $stage,
            'applicant_type' => $financeRequest->applicant_type,
            'finance_request_type' => $this->serializeFinanceRequestType($financeRequest->financeRequestType),
            'company_name' => $financeRequest->company_name,
            'country_code' => $financeRequest->country_code,
            'submitted_at' => optional($financeRequest->submitted_at)->toISOString(),
            'latest_activity_at' => optional($financeRequest->latest_activity_at)->toISOString(),
            'intake_details' => $details,
            'intake_details_json' => $details,
            'answers_count' => $financeRequest->answers_count ?? 0,
            'attachments_count' => $financeRequest->attachments_count ?? 0,
            'active_update_batch' => ($activeBatch = $this->updateService->getActiveClientBatch($financeRequest)) ? [
                'id' => $activeBatch->id,
                'status' => $activeBatch->status?->value ?? (string) $activeBatch->status,
                'reason_en' => $activeBatch->reason_en,
                'reason_ar' => $activeBatch->reason_ar,
                'opened_at' => optional($activeBatch->opened_at)->toISOString(),
                'items' => $activeBatch->items->map(function ($item) {
                    $question = $item->question;

                    return [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'field_key' => $item->field_key,
                        'question_id' => $item->question_id,
                        'label_en' => $item->label_en,
                        'label_ar' => $item->label_ar,
                        'instruction_en' => $item->instruction_en,
                        'instruction_ar' => $item->instruction_ar,
                        'status' => $item->status?->value ?? (string) $item->status,
                        'is_required' => (bool) $item->is_required,
                        'old_value_json' => $item->old_value_json,
                        'new_value_json' => $item->new_value_json,
                        'question' => $question ? [
                            'id' => $question->id,
                            'code' => $question->code,
                            'question_text' => $question->question_text,
                            'question_type' => $question->question_type,
                            'options_json' => $question->options_json ?? [],
                            'placeholder' => $question->placeholder,
                            'help_text' => $question->help_text,
                            'is_required' => (bool) $question->is_required,
                        ] : null,
                    ];
                })->values(),
            ] : null,
            'current_contract' => $financeRequest->currentContract ? [
                'id' => $financeRequest->currentContract->id,
                'version_no' => $financeRequest->currentContract->version_no,
                'status' => $financeRequest->currentContract->status?->value ?? (string) $financeRequest->currentContract->status,
                'contract_source' => $financeRequest->currentContract->contract_source,
                'client_signature_skipped' => (bool) $financeRequest->currentContract->client_signature_skipped,
                'requires_commercial_registration' => (bool) $financeRequest->currentContract->requires_commercial_registration,
                'admin_signed_at' => optional($financeRequest->currentContract->admin_signed_at)->toISOString(),
                'client_signed_at' => optional($financeRequest->currentContract->client_signed_at)->toISOString(),
                'admin_uploaded_contract_at' => optional($financeRequest->currentContract->admin_uploaded_contract_at)->toISOString(),
                'client_commercial_uploaded_at' => optional($financeRequest->currentContract->client_commercial_uploaded_at)->toISOString(),
                'admin_commercial_uploaded_at' => optional($financeRequest->currentContract->admin_commercial_uploaded_at)->toISOString(),
                'contract_pdf_path' => $financeRequest->currentContract->contract_pdf_path,
            ] : null,
        ];
    }

    private function transformRequestDetails(FinanceRequest $financeRequest): array
    {
        $details = $financeRequest->intake_details_json ?? [];
        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;
        $status = $financeRequest->status?->value ?? (string) $financeRequest->status;

        return [
            'id' => $financeRequest->id,
            'reference_number' => $financeRequest->reference_number,
            'approval_reference_number' => $financeRequest->approval_reference_number,
            'status' => $status,
            'workflow_stage' => $stage,
            'priority' => $financeRequest->priority?->value ?? (string) $financeRequest->priority,
            'applicant_type' => $financeRequest->applicant_type,
            'finance_request_type' => $this->serializeFinanceRequestType($financeRequest->financeRequestType),
            'company_name' => $financeRequest->company_name,
            'country_code' => $financeRequest->country_code,
            'submitted_at' => optional($financeRequest->submitted_at)->toISOString(),
            'approved_at' => optional($financeRequest->approved_at)->toISOString(),
            'latest_activity_at' => optional($financeRequest->latest_activity_at)->toISOString(),
            'intake_details' => $details,
            'intake_details_json' => $details,
            'can_sign' => $financeRequest->currentContract
                && ($financeRequest->currentContract->status?->value ?? (string) $financeRequest->currentContract->status) === 'admin_signed'
                && $stage === FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
            'can_upload_client_commercial_contract' => $financeRequest->currentContract
                && (bool) $financeRequest->currentContract->requires_commercial_registration
                && $stage === FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
            'can_upload_documents' => in_array($stage, [
                FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
            ], true),
            'can_submit_client_updates' => $stage === FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
            'active_update_batch' => ($activeBatch = $this->updateService->getActiveClientBatch($financeRequest)) ? [
                'id' => $activeBatch->id,
                'status' => $activeBatch->status?->value ?? (string) $activeBatch->status,
                'reason_en' => $activeBatch->reason_en,
                'reason_ar' => $activeBatch->reason_ar,
                'opened_at' => optional($activeBatch->opened_at)->toISOString(),
                'items' => $activeBatch->items->map(function ($item) {
                    $question = $item->question;

                    return [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'field_key' => $item->field_key,
                        'question_id' => $item->question_id,
                        'label_en' => $item->label_en,
                        'label_ar' => $item->label_ar,
                        'instruction_en' => $item->instruction_en,
                        'instruction_ar' => $item->instruction_ar,
                        'status' => $item->status?->value ?? (string) $item->status,
                        'is_required' => (bool) $item->is_required,
                        'old_value_json' => $item->old_value_json,
                        'new_value_json' => $item->new_value_json,
                        'question' => $question ? [
                            'id' => $question->id,
                            'code' => $question->code,
                            'question_text' => $question->question_text,
                            'question_type' => $question->question_type,
                            'options_json' => $question->options_json ?? [],
                            'placeholder' => $question->placeholder,
                            'help_text' => $question->help_text,
                            'is_required' => (bool) $question->is_required,
                        ] : null,
                    ];
                })->values(),
            ] : null,
            'current_contract' => $financeRequest->currentContract ? [
                'id' => $financeRequest->currentContract->id,
                'version_no' => $financeRequest->currentContract->version_no,
                'status' => $financeRequest->currentContract->status?->value ?? (string) $financeRequest->currentContract->status,
                'contract_source' => $financeRequest->currentContract->contract_source,
                'client_signature_skipped' => (bool) $financeRequest->currentContract->client_signature_skipped,
                'requires_commercial_registration' => (bool) $financeRequest->currentContract->requires_commercial_registration,
                'admin_signed_at' => optional($financeRequest->currentContract->admin_signed_at)->toISOString(),
                'client_signed_at' => optional($financeRequest->currentContract->client_signed_at)->toISOString(),
                'admin_uploaded_contract_at' => optional($financeRequest->currentContract->admin_uploaded_contract_at)->toISOString(),
                'client_commercial_uploaded_at' => optional($financeRequest->currentContract->client_commercial_uploaded_at)->toISOString(),
                'admin_commercial_uploaded_at' => optional($financeRequest->currentContract->admin_commercial_uploaded_at)->toISOString(),
                'contract_pdf_path' => $financeRequest->currentContract->contract_pdf_path,
            ] : null,
            'answers' => $financeRequest->answers
                ->sortBy(fn (RequestAnswer $answer) => $answer->question?->sort_order ?? 0)
                ->values()
                ->map(fn (RequestAnswer $answer) => [
                    'id' => $answer->id,
                    'question_id' => $answer->question_id,
                    'question_text' => $answer->question?->question_text,
                    'question_type' => $answer->question?->question_type,
                    'answer_text' => $answer->answer_text,
                    'answer_value_json' => $answer->answer_value_json,
                ]),
            'attachments' => $financeRequest->attachments->map(fn (RequestAttachment $attachment) => [
                'id' => $attachment->id,
                'category' => $attachment->category,
                'file_name' => $attachment->file_name,
                'file_path' => $attachment->file_path,
                'disk' => $attachment->disk,
                'mime_type' => $attachment->mime_type,
                'file_extension' => $attachment->file_extension,
                'file_size' => $attachment->file_size,
                'uploaded_at' => optional($attachment->created_at)->toISOString(),
            ])->values(),
           'shareholders' => $financeRequest->shareholders->map(fn (FinanceRequestShareholder $shareholder) => [
                        'id' => $shareholder->id,
                        'shareholder_name' => $shareholder->shareholder_name,
                        'phone_country_code' => $shareholder->phone_country_code,
                        'phone_number' => $shareholder->phone_number,
                        'id_number' => $shareholder->id_number,
                        'id_file_name' => $shareholder->id_file_name,
                        'id_file_path' => $shareholder->id_file_path,
                        'disk' => $shareholder->disk,
                        'mime_type' => $shareholder->mime_type,
                        'file_extension' => $shareholder->file_extension,
                        'file_size' => $shareholder->file_size,
                        'sort_order' => $shareholder->sort_order,
                        'uploaded_at' => optional($shareholder->created_at)->toISOString(),
                    ])->values(),
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->map(function (array $item) {
                $upload = $item['upload'] ?? null;
                $uploads = collect($item['uploads'] ?? []);

                if ($uploads->isEmpty() && $upload) {
                    $uploads = collect([$upload]);
                }

                return [
                    'document_upload_step_id' => $item['document_upload_step_id'],
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'status' => $item['status'],
                    'is_required' => $item['is_required'],
                    'is_multiple' => (bool) ($item['is_multiple'] ?? false),
                    'is_uploaded' => $item['is_uploaded'],
                    'can_client_upload' => $item['can_client_upload'],
                    'is_change_requested' => $item['is_change_requested'],
                    'rejection_reason' => $item['rejection_reason'],
                    'uploads_count' => (int) ($item['uploads_count'] ?? 0),
                    'accepted_uploads_count' => (int) ($item['accepted_uploads_count'] ?? 0),
                    'uploads' => $uploads->map(function ($uploadItem) {
                        $id = is_array($uploadItem) ? ($uploadItem['id'] ?? null) : ($uploadItem->id ?? null);
                        $fileName = is_array($uploadItem) ? ($uploadItem['file_name'] ?? null) : ($uploadItem->file_name ?? null);
                        $filePath = is_array($uploadItem) ? ($uploadItem['file_path'] ?? null) : ($uploadItem->file_path ?? null);
                        $disk = is_array($uploadItem) ? ($uploadItem['disk'] ?? null) : ($uploadItem->disk ?? null);
                        $status = is_array($uploadItem)
                            ? (($uploadItem['status'] ?? null) ?: '')
                            : ($uploadItem->status?->value ?? (string) ($uploadItem->status ?? ''));
                        $uploadedAt = is_array($uploadItem)
                            ? ($uploadItem['uploaded_at'] ?? null)
                            : optional($uploadItem->uploaded_at)->toISOString();

                        return [
                            'id' => $id,
                            'file_name' => $fileName,
                            'file_path' => $filePath,
                            'disk' => $disk,
                            'status' => $status,
                            'uploaded_at' => $uploadedAt,
                        ];
                    })->values(),
                    'upload' => $upload ? [
                        'id' => is_array($upload) ? ($upload['id'] ?? null) : ($upload->id ?? null),
                        'file_name' => is_array($upload) ? ($upload['file_name'] ?? null) : ($upload->file_name ?? null),
                        'file_path' => is_array($upload) ? ($upload['file_path'] ?? null) : ($upload->file_path ?? null),
                        'disk' => is_array($upload) ? ($upload['disk'] ?? null) : ($upload->disk ?? null),
                        'status' => is_array($upload)
                            ? (($upload['status'] ?? null) ?: '')
                            : ($upload->status?->value ?? (string) ($upload->status ?? '')),
                        'uploaded_at' => is_array($upload)
                            ? ($upload['uploaded_at'] ?? null)
                            : optional($upload->uploaded_at)->toISOString(),
                    ] : null,
                ];
            })->values(),
            'additional_document_requests' => $financeRequest->additionalDocuments->map(fn (RequestAdditionalDocument $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'reason' => $document->reason,
                'status' => $document->status?->value ?? (string) $document->status,
                'file_name' => $document->file_name,
                'file_path' => $document->file_path,
                'disk' => $document->disk,
                'requested_at' => optional($document->requested_at)->toISOString(),
                'uploaded_at' => optional($document->uploaded_at)->toISOString(),
                'rejection_reason' => $document->rejection_reason,
                'can_client_upload' => in_array($document->status?->value ?? (string) $document->status, [
                    RequestAdditionalDocumentStatus::PENDING->value,
                    RequestAdditionalDocumentStatus::REJECTED->value,
                ], true),
            ])->values(),
            'comments' => $financeRequest->comments
                ->filter(fn ($comment) => ($comment->visibility?->value ?? (string) $comment->visibility) === RequestCommentVisibility::CLIENT_VISIBLE->value)
                ->values()
                ->map(fn ($comment) => [
                    'id' => $comment->id,
                    'comment_text' => $comment->comment_text,
                    'visibility' => $comment->visibility?->value ?? (string) $comment->visibility,
                    'created_at' => optional($comment->created_at)->toISOString(),
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'email' => $comment->user->email,
                    ] : null,
                ]),
        ];
    }
    private function serializeActiveFinanceRequestTypes(): array
    {
        return FinanceRequestType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(fn (FinanceRequestType $type) => $this->serializeFinanceRequestType($type))
            ->values()
            ->all();
    }

    private function serializeFinanceRequestType(?FinanceRequestType $financeRequestType): ?array
    {
        if (! $financeRequestType) {
            return null;
        }

        return [
            'id' => $financeRequestType->id,
            'slug' => $financeRequestType->slug,
            'name_en' => $financeRequestType->name_en,
            'name_ar' => $financeRequestType->name_ar,
            'description_en' => $financeRequestType->description_en,
            'description_ar' => $financeRequestType->description_ar,
            'is_active' => (bool) $financeRequestType->is_active,
            'sort_order' => (int) $financeRequestType->sort_order,
        ];
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

}
