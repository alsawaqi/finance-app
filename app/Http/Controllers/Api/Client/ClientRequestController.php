<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\FinanceRequestPriority;
use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\RequestAdditionalDocumentStatus;
use App\Enums\RequestDocumentUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientFinanceRequestRequest;
use App\Http\Requests\Client\UploadAdditionalDocumentRequest;
use App\Http\Requests\Client\UploadRequiredDocumentRequest;
use App\Models\DocumentUploadStep;
use App\Models\FinanceRequest;
use App\Models\RequestDocumentUpload;
use App\Models\FinanceRequestShareholder;
use App\Models\RequestAdditionalDocument;
use App\Models\RequestAnswer;
use App\Models\RequestAttachment;
use App\Models\RequestQuestion;
use App\Models\RequestTimeline;
use App\Services\FinanceRequestDocumentChecklistService;
use App\Services\FinanceRequestWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientRequestController extends Controller
{
    public function __construct(
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
        private readonly FinanceRequestWorkflowService $workflowService,
    ) {
    }

    public function questions(): JsonResponse
    {
        $questions = RequestQuestion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (RequestQuestion $question) => [
                'id' => $question->id,
                'code' => $question->code,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
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
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $requests = FinanceRequest::query()
            ->with(['currentContract:id,finance_request_id,version_no,status,admin_signed_at,client_signed_at,contract_pdf_path'])
            ->withCount(['answers', 'attachments'])
            ->where('user_id', $request->user()->id)
            ->latest('submitted_at')
            ->latest('id')
            ->get()
            ->map(fn (FinanceRequest $financeRequest) => $this->transformRequestSummary($financeRequest));

        return response()->json([
            'requests' => $requests,
        ]);
    }

   public function store(StoreClientFinanceRequestRequest $request): JsonResponse
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
        $country = trim((string) Arr::get($details, 'country', ''));
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

        $financeRequest = FinanceRequest::create([
            'reference_number' => 'TMP-' . Str::upper(Str::random(12)),
            'user_id' => $user->id,
            'applicant_type' => $applicantType,
            'company_name' => $companyName,
            'status' => FinanceRequestStatus::SUBMITTED,
            'workflow_stage' => FinanceRequestWorkflowStage::REVIEW,
            'priority' => FinanceRequestPriority::NORMAL,
            'submitted_at' => $now,
            'latest_activity_at' => $now,
            'intake_details_json' => [
                    'applicant_name' => $applicantName,
                    'full_name' => $applicantName,
                    'name' => $applicantName,
                    'country' => $country,
                    'country_code' => $country,
                    'requested_amount' => $requestedAmount,
                    'finance_type' => $applicantType,
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

        RequestTimeline::create([
            'finance_request_id' => $financeRequest->id,
            'actor_user_id' => $user->id,
            'event_type' => 'request_submitted',
            'event_title' => 'Request submitted',
            'event_description' => 'Client submitted a new finance request and it is now waiting for admin review.',
            'metadata_json' => [
                'reference_number' => $financeRequest->reference_number,
                'answer_count' => $answers->count(),
                'attachment_count' => count($storedAttachments),
                'shareholder_count' => count($storedShareholders),
                'applicant_type' => $applicantType,
                'requested_amount' => $requestedAmount,
                'country' => $country,
                'company_name' => $companyName,
                'email' => $email,
                'phone_country_code' => $phoneCountryCode,
                'phone_number' => $phoneNumber,
                'unified_number' => $unifiedNumber,
                'national_address_number' => $nationalAddressNumber,
                'company_cr_number' => $companyCrNumber,
                'address' => $address,
            ],
            'created_at' => $now,
        ]);

        return $financeRequest->fresh(['answers.question', 'attachments', 'shareholders', 'additionalDocuments', 'currentContract']);
    });

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
            'shareholders',
            'additionalDocuments',
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
            FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
            FinanceRequestWorkflowStage::PROCESSING->value,
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

        if ($latestUpload && $latestStatus !== RequestDocumentUploadStatus::REJECTED->value) {
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

            RequestTimeline::create([
                'finance_request_id' => $financeRequest->id,
                'actor_user_id' => $request->user()->id,
                'event_type' => 'request.required_document_uploaded',
                'event_title' => 'Required document uploaded',
                'event_description' => 'Client uploaded the required document: ' . $step->name . '.',
                'metadata_json' => [
                    'document_upload_step_id' => $step->id,
                    'document_name' => $step->name,
                    'is_resubmission' => $latestUpload !== null,
                    'previous_upload_id' => $latestUpload?->id,
                ],
                'created_at' => now(),
            ]);

            $this->workflowService->syncAfterRequiredDocuments($financeRequest->fresh());
        });

        return response()->json([
            'message' => 'Required document uploaded successfully.',
            'request' => $this->transformRequestDetails(
                $financeRequest->fresh(['answers.question', 'attachments', 'client', 'currentContract', 'shareholders', 'additionalDocuments'])
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

            RequestTimeline::create([
                'finance_request_id' => $financeRequest->id,
                'actor_user_id' => $request->user()->id,
                'event_type' => 'request.additional_document_uploaded',
                'event_title' => 'Additional document uploaded',
                'event_description' => 'Client uploaded the requested additional document: ' . $additionalDocument->title . '.',
                'metadata_json' => [
                    'additional_document_id' => $additionalDocument->id,
                    'title' => $additionalDocument->title,
                ],
                'created_at' => now(),
            ]);

            $this->workflowService->syncAfterAdditionalDocuments($financeRequest->fresh());
        });

        return response()->json([
            'message' => 'Additional document uploaded successfully.',
            'request' => $this->transformRequestDetails(
                $financeRequest->fresh(['answers.question', 'attachments', 'client', 'currentContract', 'shareholders', 'additionalDocuments'])
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
            'company_name' => $financeRequest->company_name,
            'submitted_at' => optional($financeRequest->submitted_at)->toISOString(),
            'latest_activity_at' => optional($financeRequest->latest_activity_at)->toISOString(),
            'intake_details' => $details,
            'intake_details_json' => $details,
            'answers_count' => $financeRequest->answers_count ?? 0,
            'attachments_count' => $financeRequest->attachments_count ?? 0,
            'current_contract' => $financeRequest->currentContract ? [
                'id' => $financeRequest->currentContract->id,
                'version_no' => $financeRequest->currentContract->version_no,
                'status' => $financeRequest->currentContract->status?->value ?? (string) $financeRequest->currentContract->status,
                'admin_signed_at' => optional($financeRequest->currentContract->admin_signed_at)->toISOString(),
                'client_signed_at' => optional($financeRequest->currentContract->client_signed_at)->toISOString(),
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
            'company_name' => $financeRequest->company_name,
            'submitted_at' => optional($financeRequest->submitted_at)->toISOString(),
            'approved_at' => optional($financeRequest->approved_at)->toISOString(),
            'latest_activity_at' => optional($financeRequest->latest_activity_at)->toISOString(),
            'intake_details' => $details,
            'intake_details_json' => $details,
            'can_sign' => $financeRequest->currentContract && ($financeRequest->currentContract->status?->value ?? (string) $financeRequest->currentContract->status) === 'admin_signed',
            'can_upload_documents' => in_array($stage, [
                FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
            ], true),
            'current_contract' => $financeRequest->currentContract ? [
                'id' => $financeRequest->currentContract->id,
                'version_no' => $financeRequest->currentContract->version_no,
                'status' => $financeRequest->currentContract->status?->value ?? (string) $financeRequest->currentContract->status,
                'admin_signed_at' => optional($financeRequest->currentContract->admin_signed_at)->toISOString(),
                'client_signed_at' => optional($financeRequest->currentContract->client_signed_at)->toISOString(),
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
                $upload = $item['upload'];

                return [
                    'document_upload_step_id' => $item['document_upload_step_id'],
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'status' => $item['status'],
                    'is_required' => $item['is_required'],
                    'is_uploaded' => $item['is_uploaded'],
                    'can_client_upload' => $item['can_client_upload'],
                    'is_change_requested' => $item['is_change_requested'],
                    'rejection_reason' => $item['rejection_reason'],
                    'upload' => $upload ? [
                        'id' => $upload->id,
                        'file_name' => $upload->file_name,
                        'file_path' => $upload->file_path,
                        'disk' => $upload->disk,
                        'status' => $upload->status?->value ?? (string) $upload->status,
                        'uploaded_at' => optional($upload->uploaded_at)->toISOString(),
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
            ])->values(),
        ];
    }
}
