<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\RequestAdditionalDocumentStatus;
use App\Enums\RequestDocumentUploadStatus;
use App\Http\Controllers\Controller;
use App\Models\FinanceRequest;
use App\Models\RequestAnswer;
use App\Models\RequestAdditionalDocument;
use App\Models\RequestAttachment;
use App\Models\RequestDocumentUpload;
use App\Models\RequestQuestion;
use App\Services\FinanceRequestDocumentChecklistService;
use App\Services\FinanceRequestStaffQuestionService;
use App\Services\FinanceRequestWorkflowService;
use App\Support\RequestTimelineLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceRequestDirectEditController extends Controller
{
    private const INTAKE_FIELDS = [
        'finance_type',
        'requested_amount',
        'company_name',
        'company_cr_number',
        'email',
        'phone_country_code',
        'phone_number',
        'unified_number',
        'national_address_number',
        'address',
        'notes',
        'finance_request_type_id',
        'country',
        'country_code',
    ];

    public function __construct(
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
        private readonly FinanceRequestStaffQuestionService $staffQuestionService,
        private readonly FinanceRequestWorkflowService $workflowService,
    ) {
    }

    public function update(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $validated = $request->validate([
            'intake_details' => ['nullable', 'array'],
            'intake_details.finance_type' => ['nullable', 'string', Rule::in(['individual', 'company'])],
            'intake_details.requested_amount' => ['nullable', 'numeric', 'min:0'],
            'intake_details.company_name' => ['nullable', 'string', 'max:255'],
            'intake_details.company_cr_number' => ['nullable', 'string', 'max:255'],
            'intake_details.email' => ['nullable', 'email', 'max:255'],
            'intake_details.phone_country_code' => ['nullable', 'string', 'max:10'],
            'intake_details.phone_number' => ['nullable', 'string', 'max:30'],
            'intake_details.unified_number' => ['nullable', 'string', 'max:100'],
            'intake_details.national_address_number' => ['nullable', 'string', 'max:100'],
            'intake_details.address' => ['nullable', 'string', 'max:2000'],
            'intake_details.notes' => ['nullable', 'string', 'max:2000'],
            'intake_details.finance_request_type_id' => ['nullable', 'integer', 'exists:finance_request_types,id'],
            'intake_details.country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'intake_details.country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required_with:answers', 'integer', 'exists:request_questions,id'],
            'answers.*.value' => ['nullable'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $intakeDetails = $validated['intake_details'] ?? [];
        $answers = $validated['answers'] ?? [];

        if ($intakeDetails === [] && $answers === []) {
            throw ValidationException::withMessages([
                'direct_edit' => 'Provide at least one request field or questionnaire answer to update.',
            ]);
        }

        DB::transaction(function () use ($financeRequest, $request, $intakeDetails, $answers, $validated): void {
            $changedIntakeFields = $this->applyIntakeDetails($financeRequest, $intakeDetails);
            $changedAnswerIds = $this->applyAnswers($financeRequest, $answers, $request->user()?->id);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.admin_direct_edit',
                $request->user()?->id,
                'Admin directly edited request information',
                'Admin directly edited request information',
                trim((string) ($validated['note'] ?? '')) ?: 'The admin directly updated request data without opening a client update batch.',
                trim((string) ($validated['note'] ?? '')) ?: 'The admin directly updated request data without opening a client update batch.',
                [
                    'intake_fields' => $changedIntakeFields,
                    'answer_question_ids' => $changedAnswerIds,
                ],
            );
        });

        $fresh = $financeRequest->fresh();

        return response()->json([
            'message' => 'Request information updated successfully.',
            ...$this->responsePayload($fresh),
        ]);
    }

    public function uploadAttachment(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\\-]+$/'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $category = strtolower(trim((string) $validated['category']));

        $attachment = DB::transaction(function () use ($financeRequest, $request, $file, $category): RequestAttachment {
            $path = $file->store('request-attachments/admin-direct', 'public');

            $attachment = RequestAttachment::create([
                'finance_request_id' => $financeRequest->id,
                'category' => $category,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'file_extension' => $file->getClientOriginalExtension() ?: $file->extension(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()?->id,
            ]);

            $financeRequest->forceFill([
                'latest_activity_at' => now(),
            ])->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.admin_attachment_uploaded',
                $request->user()?->id,
                'Admin uploaded request attachment',
                'Admin uploaded request attachment',
                'The admin uploaded a request attachment: ' . $attachment->file_name . '.',
                'The admin uploaded a request attachment.',
                [
                    'attachment_id' => $attachment->id,
                    'category' => $category,
                    'file_name' => $attachment->file_name,
                ],
            );

            return $attachment;
        });

        $fresh = $financeRequest->fresh();

        return response()->json([
            'message' => 'Request attachment uploaded successfully.',
            'attachment' => $attachment->fresh('uploader:id,name'),
            ...$this->responsePayload($fresh),
        ], 201);
    }

    public function deleteAttachment(
        Request $request,
        FinanceRequest $financeRequest,
        RequestAttachment $attachment,
    ): JsonResponse {
        abort_unless((int) $attachment->finance_request_id === (int) $financeRequest->id, 404);

        DB::transaction(function () use ($financeRequest, $request, $attachment): void {
            $fileName = $attachment->file_name;
            $filePath = $attachment->file_path;
            $disk = $attachment->disk ?: 'public';
            $attachmentId = $attachment->id;
            $category = $attachment->category;

            if (filled($filePath)) {
                Storage::disk($disk)->delete($filePath);
            }

            $attachment->delete();

            $financeRequest->forceFill([
                'latest_activity_at' => now(),
            ])->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.admin_attachment_deleted',
                $request->user()?->id,
                'Admin removed request attachment',
                'Admin removed request attachment',
                'The admin removed a request attachment: ' . ($fileName ?: 'attachment') . '.',
                'The admin removed a request attachment.',
                [
                    'attachment_id' => $attachmentId,
                    'category' => $category,
                    'file_name' => $fileName,
                ],
            );
        });

        $fresh = $financeRequest->fresh();

        return response()->json([
            'message' => 'Request attachment removed successfully.',
            ...$this->responsePayload($fresh),
        ]);
    }

    public function uploadRequiredDocument(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $validated = $request->validate([
            'document_upload_step_id' => ['required', 'integer', 'exists:document_upload_steps,id'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        $step = $this->documentChecklistService->findRequiredStepForRequest(
            $financeRequest,
            (int) $validated['document_upload_step_id'],
        );
        abort_unless($step, 404, 'The selected required document is not available for this request type.');

        /** @var UploadedFile $file */
        $file = $validated['file'];

        $upload = DB::transaction(function () use ($financeRequest, $request, $step, $file): RequestDocumentUpload {
            $latestUpload = RequestDocumentUpload::query()
                ->where('finance_request_id', $financeRequest->id)
                ->where('document_upload_step_id', $step->id)
                ->latest('id')
                ->first();

            $path = $file->store('request-documents/required', 'public');

            if (! (bool) $step->is_multiple) {
                RequestDocumentUpload::query()
                    ->where('finance_request_id', $financeRequest->id)
                    ->where('document_upload_step_id', $step->id)
                    ->where('status', '!=', RequestDocumentUploadStatus::REJECTED->value)
                    ->update([
                        'status' => RequestDocumentUploadStatus::REJECTED->value,
                        'reviewed_by' => $request->user()?->id,
                        'reviewed_at' => now(),
                        'rejection_reason' => 'Replaced by admin.',
                        'updated_at' => now(),
                    ]);
            }

            $upload = RequestDocumentUpload::create([
                'finance_request_id' => $financeRequest->id,
                'document_upload_step_id' => $step->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'file_extension' => $file->getClientOriginalExtension() ?: $file->extension(),
                'file_size' => $file->getSize(),
                'status' => RequestDocumentUploadStatus::UPLOADED,
                'uploaded_by' => $request->user()?->id,
                'uploaded_at' => now(),
            ]);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.required_document_uploaded_by_admin',
                $request->user()?->id,
                'Required document uploaded by admin',
                'Required document uploaded by admin',
                'Admin uploaded a required document: ' . $step->name . '.',
                'Admin uploaded a required document.',
                [
                    'document_upload_step_id' => $step->id,
                    'document_upload_id' => $upload->id,
                    'document_name' => $step->name,
                    'file_name' => $upload->file_name,
                    'is_multiple_step' => (bool) $step->is_multiple,
                    'is_resubmission' => $latestUpload !== null,
                    'previous_upload_id' => $latestUpload?->id,
                ],
            );

            $this->syncAfterDirectDocumentEdit($financeRequest->fresh(), 'required');

            return $upload;
        });

        $fresh = $financeRequest->fresh();

        return response()->json([
            'message' => 'Required document uploaded successfully.',
            'upload' => $upload->fresh(['uploader:id,name,email', 'documentUploadStep:id,name,code']),
            ...$this->responsePayload($fresh),
        ], 201);
    }

    public function deleteRequiredDocumentUpload(
        Request $request,
        FinanceRequest $financeRequest,
        RequestDocumentUpload $requestDocumentUpload,
    ): JsonResponse {
        abort_unless((int) $requestDocumentUpload->finance_request_id === (int) $financeRequest->id, 404);

        DB::transaction(function () use ($financeRequest, $request, $requestDocumentUpload): void {
            $fileName = $requestDocumentUpload->file_name;
            $filePath = $requestDocumentUpload->file_path;
            $disk = $requestDocumentUpload->disk ?: 'public';
            $uploadId = $requestDocumentUpload->id;
            $step = $requestDocumentUpload->documentUploadStep()->first();

            if (filled($filePath)) {
                Storage::disk($disk)->delete($filePath);
            }

            $requestDocumentUpload->delete();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.required_document_removed_by_admin',
                $request->user()?->id,
                'Required document removed by admin',
                'Required document removed by admin',
                'Admin removed an uploaded required document: ' . ($step?->name ?: $fileName ?: 'Required document') . '.',
                'Admin removed an uploaded required document.',
                [
                    'document_upload_step_id' => $step?->id,
                    'document_upload_id' => $uploadId,
                    'document_name' => $step?->name,
                    'file_name' => $fileName,
                ],
            );

            $this->syncAfterDirectDocumentEdit($financeRequest->fresh(), 'required');
        });

        $fresh = $financeRequest->fresh();

        return response()->json([
            'message' => 'Required document upload removed successfully.',
            ...$this->responsePayload($fresh),
        ]);
    }

    public function uploadAdditionalDocument(
        Request $request,
        FinanceRequest $financeRequest,
        RequestAdditionalDocument $additionalDocument,
    ): JsonResponse {
        abort_unless((int) $additionalDocument->finance_request_id === (int) $financeRequest->id, 404);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        $status = $additionalDocument->status?->value ?? (string) $additionalDocument->status;
        abort_if($status === RequestAdditionalDocumentStatus::CANCELLED->value, 422, 'This additional document request has been cancelled.');

        /** @var UploadedFile $file */
        $file = $validated['file'];

        DB::transaction(function () use ($financeRequest, $request, $additionalDocument, $file): void {
            if (filled($additionalDocument->file_path)) {
                Storage::disk($additionalDocument->disk ?: 'public')->delete($additionalDocument->file_path);
            }

            $path = $file->store('request-documents/additional', 'public');

            $additionalDocument->update([
                'status' => RequestAdditionalDocumentStatus::UPLOADED,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'file_extension' => $file->getClientOriginalExtension() ?: $file->extension(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()?->id,
                'uploaded_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ]);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.additional_document_uploaded_by_admin',
                $request->user()?->id,
                'Additional document uploaded by admin',
                'Additional document uploaded by admin',
                'Admin uploaded an additional document: ' . $additionalDocument->title . '.',
                'Admin uploaded an additional document.',
                [
                    'additional_document_id' => $additionalDocument->id,
                    'title' => $additionalDocument->title,
                    'file_name' => $file->getClientOriginalName(),
                ],
            );

            $this->syncAfterDirectDocumentEdit($financeRequest->fresh(), 'additional');
        });

        $fresh = $financeRequest->fresh();

        return response()->json([
            'message' => 'Additional document uploaded successfully.',
            ...$this->responsePayload($fresh),
        ]);
    }

    public function deleteAdditionalDocumentFile(
        Request $request,
        FinanceRequest $financeRequest,
        RequestAdditionalDocument $additionalDocument,
    ): JsonResponse {
        abort_unless((int) $additionalDocument->finance_request_id === (int) $financeRequest->id, 404);
        abort_unless(filled($additionalDocument->file_path), 422, 'This additional document does not have an uploaded file to remove.');

        DB::transaction(function () use ($financeRequest, $request, $additionalDocument): void {
            $fileName = $additionalDocument->file_name;
            $filePath = $additionalDocument->file_path;
            Storage::disk($additionalDocument->disk ?: 'public')->delete($filePath);

            $additionalDocument->update([
                'status' => RequestAdditionalDocumentStatus::PENDING,
                'file_name' => null,
                'file_path' => null,
                'disk' => 'public',
                'mime_type' => null,
                'file_extension' => null,
                'file_size' => null,
                'uploaded_by' => null,
                'uploaded_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ]);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.additional_document_removed_by_admin',
                $request->user()?->id,
                'Additional document removed by admin',
                'Additional document removed by admin',
                'Admin removed an uploaded additional document: ' . ($additionalDocument->title ?: $fileName ?: 'Additional document') . '.',
                'Admin removed an uploaded additional document.',
                [
                    'additional_document_id' => $additionalDocument->id,
                    'title' => $additionalDocument->title,
                    'file_name' => $fileName,
                ],
            );

            $this->syncAfterDirectDocumentEdit($financeRequest->fresh(), 'additional');
        });

        $fresh = $financeRequest->fresh();

        return response()->json([
            'message' => 'Additional document file removed successfully.',
            ...$this->responsePayload($fresh),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function applyIntakeDetails(FinanceRequest $financeRequest, array $payload): array
    {
        $details = $financeRequest->intake_details_json ?? [];
        $changedFields = [];

        foreach (self::INTAKE_FIELDS as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $value = $this->normalizeIntakeValue($field, $payload[$field]);
            $targetField = $field === 'country' ? 'country_code' : $field;

            if ($targetField === 'country_code') {
                $financeRequest->country_code = $value;
                $details['country'] = $value;
                $details['country_code'] = $value;
            } elseif ($targetField === 'finance_type') {
                $financeRequest->applicant_type = $value ?: 'individual';
                $details['finance_type'] = $value ?: 'individual';
            } elseif ($targetField === 'company_name') {
                $financeRequest->company_name = $value;
                $details['company_name'] = $value;
            } elseif ($targetField === 'finance_request_type_id') {
                $financeRequest->finance_request_type_id = $value !== null ? (int) $value : null;
                $details['finance_request_type_id'] = $value !== null ? (int) $value : null;
            } else {
                $details[$targetField] = $value;
            }

            $changedFields[] = $targetField;
        }

        if ($changedFields !== []) {
            $financeRequest->intake_details_json = $details;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();
        }

        return array_values(array_unique($changedFields));
    }

    /**
     * @return array<int, int>
     */
    private function applyAnswers(FinanceRequest $financeRequest, array $answers, ?int $actorUserId): array
    {
        $changedQuestionIds = [];

        foreach ($answers as $row) {
            $question = RequestQuestion::findOrFail((int) $row['question_id']);
            [$answerText, $answerValueJson] = $this->normalizeQuestionValue($question, Arr::get($row, 'value'));

            RequestAnswer::updateOrCreate(
                [
                    'finance_request_id' => $financeRequest->id,
                    'question_id' => $question->id,
                ],
                [
                    'answer_text' => $answerText,
                    'answer_value_json' => $answerValueJson,
                    'answered_by' => $actorUserId,
                ],
            );

            $changedQuestionIds[] = (int) $question->id;
        }

        if ($changedQuestionIds !== []) {
            $financeRequest->forceFill([
                'latest_activity_at' => now(),
            ])->save();
        }

        return array_values(array_unique($changedQuestionIds));
    }

    private function normalizeIntakeValue(string $field, mixed $value): mixed
    {
        $normalized = is_string($value) ? trim($value) : $value;

        if ($normalized === '') {
            return null;
        }

        if ($field === 'requested_amount') {
            return $normalized !== null ? (float) $normalized : null;
        }

        if ($field === 'finance_request_type_id') {
            return $normalized !== null ? (int) $normalized : null;
        }

        if (in_array($field, ['country', 'country_code'], true)) {
            return $normalized !== null ? strtoupper((string) $normalized) : null;
        }

        if ($field === 'finance_type') {
            return $normalized === 'company' ? 'company' : 'individual';
        }

        return $normalized;
    }

    /**
     * @return array{0: ?string, 1: mixed}
     */
    private function normalizeQuestionValue(RequestQuestion $question, mixed $value): array
    {
        $type = (string) $question->question_type;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return [null, null];
        }

        if ($type === 'checkbox') {
            $values = collect(is_array($value) ? $value : [$value])
                ->map(fn ($entry) => trim((string) $entry))
                ->filter(fn ($entry) => $entry !== '')
                ->values()
                ->all();

            return [implode(', ', $values), $values];
        }

        $text = is_array($value) ? implode(', ', array_map('strval', $value)) : trim((string) $value);

        if (in_array($type, ['number', 'currency'], true) && ! is_numeric($text)) {
            throw ValidationException::withMessages([
                'answers' => "{$question->question_text} must be a valid number.",
            ]);
        }

        if ($type === 'email' && filter_var($text, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'answers' => "{$question->question_text} must be a valid email address.",
            ]);
        }

        if ($type === 'date' && strtotime($text) === false) {
            throw ValidationException::withMessages([
                'answers' => "{$question->question_text} must be a valid date.",
            ]);
        }

        return [$text, in_array($type, ['select', 'radio'], true) ? $text : null];
    }

    private function syncAfterDirectDocumentEdit(FinanceRequest $financeRequest, string $documentKind): void
    {
        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;

        if (in_array($stage, [
            FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
            FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
            FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
            FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value,
        ], true)) {
            if ($documentKind === 'additional') {
                $this->workflowService->syncAfterAdditionalDocuments($financeRequest);

                return;
            }

            $this->workflowService->syncAfterRequiredDocuments($financeRequest);

            return;
        }

        $financeRequest->forceFill([
            'status' => $financeRequest->status ?: FinanceRequestStatus::ACTIVE,
            'latest_activity_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(FinanceRequest $financeRequest): array
    {
        $requestPayload = app(AdminFinanceRequestController::class)
            ->show($financeRequest)
            ->getData(true);

        return [
            'request' => $requestPayload['request'],
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($financeRequest->loadMissing('staffQuestions')),
        ];
    }
}
