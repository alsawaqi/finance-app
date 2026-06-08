<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestUnderstudyStatus;
use App\Enums\RequestAdditionalDocumentStatus;
use App\Enums\RequestCommentVisibility;
use App\Enums\RequestDocumentUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFinanceRequestUpdateBatchRequest;
use App\Http\Requests\Staff\AnswerFinanceRequestStaffQuestionRequest;
use App\Http\Requests\Staff\SaveFinanceRequestUnderstudyDraftRequest;
use App\Http\Requests\Staff\SubmitFinanceRequestUnderstudyRequest;
use App\Http\Requests\Staff\RequestRequiredDocumentChangeRequest;
use App\Http\Requests\Staff\SendFinanceRequestEmailRequest;
use App\Http\Requests\Staff\StoreAdditionalDocumentRequest;
use App\Http\Requests\Staff\StoreStaffRequestCommentRequest;
use App\Http\Requests\Staff\UploadRequiredDocumentRequest;
use App\Models\Agent;
use App\Models\Bank;
use App\Models\DocumentUploadStep;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestStaffQuestion;
use App\Models\RequestAdditionalDocument;
use App\Models\RequestComment;
use App\Models\RequestDocumentUpload;
use App\Models\User;
use App\Services\FinanceRequestAgentAssignmentService;
use App\Services\FinanceRequestDocumentChecklistService;
use App\Services\FinanceRequestEmailService;
use App\Services\FinanceRequestStaffQuestionService;
use App\Services\FinanceRequestUpdateService;
use App\Services\FinanceRequestWorkflowService;
use App\Support\RequestTimelineLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StaffRequestWorkspaceController extends Controller
{
    public function __construct(
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
        private readonly FinanceRequestWorkflowService $workflowService,
        private readonly FinanceRequestStaffQuestionService $staffQuestionService,
        private readonly FinanceRequestAgentAssignmentService $agentAssignmentService,
        private readonly FinanceRequestEmailService $requestEmailService,
        private readonly FinanceRequestUpdateService $updateService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('view assigned requests')), 403);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);

        $query = FinanceRequest::query()
            ->with([
                'client:id,name,email',
                'currentContract:id,finance_request_id,version_no,status,client_signed_at',
                'financeRequestType:id,slug,name_en,name_ar',
                'assignments' => fn ($assignmentQuery) => $assignmentQuery
                    ->where('is_active', true)
                    ->with('staff:id,name,email')
                    ->orderByDesc('is_primary')
                    ->orderBy('assigned_at'),
            ])
            ->withCount('comments')
            ->whereIn('workflow_stage', [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                FinanceRequestWorkflowStage::UNDERSTUDY->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
                FinanceRequestWorkflowStage::ASSIGNED_TO_STAFF->value,
                FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
            ]);

        if (! $user->hasRole('admin')) {
            $query->where(function ($innerQuery) use ($user) {
                $innerQuery->where('primary_staff_id', $user->id)
                    ->orWhereHas('assignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery
                            ->where('staff_id', $user->id)
                            ->where('is_active', true);
                    });
            });
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($searchQuery) use ($term) {
                $searchQuery->where('reference_number', 'like', "%{$term}%")
                    ->orWhere('approval_reference_number', 'like', "%{$term}%")
                    ->orWhereHas('client', function ($clientQuery) use ($term) {
                        $clientQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('workflow_stage')) {
            $query->where('workflow_stage', (string) $request->input('workflow_stage'));
        }

        $requestsPaginator = $query
            ->orderByRaw("CASE 
                WHEN workflow_stage = ? THEN 0 
                WHEN workflow_stage = ? THEN 1 
                WHEN workflow_stage = ? THEN 2 
                WHEN workflow_stage = ? THEN 3 
                WHEN workflow_stage = ? THEN 4 
                WHEN workflow_stage = ? THEN 5 
                WHEN workflow_stage = ? THEN 6 
                ELSE 7 END", [
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                FinanceRequestWorkflowStage::UNDERSTUDY->value,
                FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
            ])
            ->orderByDesc('latest_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'requests' => collect($requestsPaginator->items())->values(),
            'pagination' => $this->paginationMeta($requestsPaginator),
        ]);
    }

    public function show(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $this->ensureVisibleToUser($request->user(), $financeRequest);

        $financeRequest = $this->loadRequestGraph($financeRequest, $request->user());

        return response()->json([
            'request' => $financeRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($financeRequest),
        ]);
    }

    public function storeComment(StoreStaffRequestCommentRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        abort_unless($user && ($user->hasRole('admin') || $user->can('add internal comments')), 403);

        $comment = DB::transaction(function () use ($request, $financeRequest, $user) {
            $comment = RequestComment::create([
                'finance_request_id' => $financeRequest->id,
                'user_id' => $user?->id,
                'parent_id' => null,
                'comment_text' => (string) $request->input('comment_text'),
                'visibility' => RequestCommentVisibility::from((string) $request->input('visibility')),
            ]);

            $financeRequest->latest_activity_at = now();
            $financeRequest->save();
            $this->workflowService->syncAfterAdditionalDocuments($financeRequest);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.comment_added',
                $user?->id,
                'Request comment added',
                'تمت إضافة تعليق على الطلب',
                str($comment->comment_text)->limit(240)->toString(),
                'تمت إضافة تعليق جديد على الطلب.',
                [
                    'comment_id' => $comment->id,
                    'visibility' => $comment->visibility?->value,
                ],
            );

            return $comment->load('user:id,name,email');
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Comment added successfully.',
            'comment' => $comment,
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ], 201);
    }

    public function answerStaffQuestion(
        AnswerFinanceRequestStaffQuestionRequest $request,
        FinanceRequest $financeRequest,
        FinanceRequestStaffQuestion $staffQuestion,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureUnderstudyEditable($financeRequest);

        if ($staffQuestion->assigned_to && (int) $staffQuestion->assigned_to !== (int) $user?->id && ! $user?->hasRole('admin')) {
            abort(403, 'This staff question is assigned to another staff member.');
        }

        $answeredQuestion = DB::transaction(function () use ($request, $financeRequest, $staffQuestion, $user) {
            $answeredQuestion = $this->staffQuestionService->answerQuestion(
                $financeRequest,
                $staffQuestion,
                $user,
                $request->validated(),
            );

            $this->workflowService->syncStaffQuestionStage($financeRequest, $user?->id);

            return $answeredQuestion;
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Staff study question answered successfully.',
            'staff_question' => $answeredQuestion,
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }


    public function saveUnderstudyDraft(
        SaveFinanceRequestUnderstudyDraftRequest $request,
        FinanceRequest $financeRequest,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureUnderstudyEditable($financeRequest);

        $updatedRequest = DB::transaction(function () use ($financeRequest, $user, $request) {
            return $this->staffQuestionService->saveStudyDraft(
                $financeRequest,
                $user,
                $request->validated('understudy_note'),
            );
        });

        $freshRequest = $this->loadRequestGraph($updatedRequest->fresh(), $user);

        return response()->json([
            'message' => 'Understudy draft saved successfully.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }

    public function submitUnderstudy(
        SubmitFinanceRequestUnderstudyRequest $request,
        FinanceRequest $financeRequest,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureUnderstudyEditable($financeRequest);

        $updatedRequest = DB::transaction(function () use ($financeRequest, $user, $request) {
            return $this->staffQuestionService->submitStudy(
                $financeRequest,
                $user,
                (string) $request->validated('understudy_note'),
            );
        });

        $freshRequest = $this->loadRequestGraph($updatedRequest->fresh(), $user);

        return response()->json([
            'message' => 'Understudy submitted to admin successfully.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }

    public function requestRequiredDocumentChange(
        RequestRequiredDocumentChangeRequest $request,
        FinanceRequest $financeRequest,
        DocumentUploadStep $documentUploadStep,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureCanManageClientCorrections($user, $financeRequest);
        abort_unless($documentUploadStep->is_active && $documentUploadStep->is_required, 404);
        abort_unless($this->documentChecklistService->isStepApplicableForRequest($financeRequest, $documentUploadStep), 404);

        $latestUpload = RequestDocumentUpload::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('document_upload_step_id', $documentUploadStep->id)
            ->latest('id')
            ->first();

        abort_if(! $latestUpload, 422, 'The client has not uploaded this required document yet.');

        $latestStatus = $latestUpload->status?->value ?? (string) $latestUpload->status;
        abort_if($latestStatus === RequestDocumentUploadStatus::REJECTED->value, 422, 'A change has already been requested for this document.');

        $reason = trim((string) $request->input('reason'));

        DB::transaction(function () use ($financeRequest, $documentUploadStep, $latestUpload, $user, $reason) {
            $latestUpload->update([
                'status' => RequestDocumentUploadStatus::REJECTED,
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->moveToClientDocumentCorrectionStage($financeRequest);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.required_document_change_requested',
                $user?->id,
                'Required document change requested',
                'تم طلب تعديل المستند المطلوب',
                'Staff requested a corrected upload for: ' . $documentUploadStep->name . '.',
                'طلب الموظف رفع نسخة مصححة من المستند: ' . $documentUploadStep->name . '.',
                [
                    'document_upload_step_id' => $documentUploadStep->id,
                    'document_upload_id' => $latestUpload->id,
                    'document_name' => $documentUploadStep->name,
                    'reason' => $reason,
                ],
            );
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'The client can now upload a corrected version of this required document.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }

    public function uploadRequiredDocumentOnBehalf(
        UploadRequiredDocumentRequest $request,
        FinanceRequest $financeRequest,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureCanManageClientCorrections($user, $financeRequest);

        $step = $this->documentChecklistService->findRequiredStepForRequest(
            $financeRequest,
            (int) $request->integer('document_upload_step_id'),
        );
        abort_unless($step, 404, 'The selected required document is not available for this request type.');

        $latestUpload = RequestDocumentUpload::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('document_upload_step_id', $step->id)
            ->latest('id')
            ->first();

        $file = $request->file('file');

        DB::transaction(function () use ($financeRequest, $step, $file, $user, $latestUpload): void {
            $path = $file->store('request-documents/required', 'public');

            if (! (bool) $step->is_multiple) {
                RequestDocumentUpload::query()
                    ->where('finance_request_id', $financeRequest->id)
                    ->where('document_upload_step_id', $step->id)
                    ->where('status', '!=', RequestDocumentUploadStatus::REJECTED->value)
                    ->update([
                        'status' => RequestDocumentUploadStatus::REJECTED->value,
                        'reviewed_by' => $user?->id,
                        'reviewed_at' => now(),
                        'rejection_reason' => 'Replaced by the team.',
                        'updated_at' => now(),
                    ]);
            }

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
                'uploaded_by' => $user?->id,
                'uploaded_at' => now(),
            ]);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.required_document_uploaded_by_staff',
                $user?->id,
                'Required document uploaded by staff',
                'تم رفع المستند المطلوب بواسطة الموظف',
                'Staff uploaded a required document on behalf of the client: ' . $step->name . '.',
                'قام الموظف برفع مستند مطلوب نيابةً عن العميل: ' . $step->name . '.',
                [
                    'document_upload_step_id' => $step->id,
                    'document_name' => $step->name,
                    'is_multiple_step' => (bool) $step->is_multiple,
                    'is_resubmission' => $latestUpload !== null,
                    'previous_upload_id' => $latestUpload?->id,
                ],
            );

            $this->syncAfterDirectDocumentEdit($financeRequest->fresh(), 'required');
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Required document uploaded successfully.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }

    public function deleteRequiredDocumentUpload(
        Request $request,
        FinanceRequest $financeRequest,
        RequestDocumentUpload $requestDocumentUpload,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureCanManageClientCorrections($user, $financeRequest);
        abort_unless((int) $requestDocumentUpload->finance_request_id === (int) $financeRequest->id, 404);

        DB::transaction(function () use ($financeRequest, $requestDocumentUpload, $user): void {
            $fileName = $requestDocumentUpload->file_name;
            $filePath = $requestDocumentUpload->file_path;
            $disk = $requestDocumentUpload->disk ?: 'public';
            $step = $requestDocumentUpload->documentUploadStep()->first();

            if (filled($filePath)) {
                Storage::disk($disk)->delete($filePath);
            }

            $requestDocumentUpload->delete();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.required_document_removed_by_staff',
                $user?->id,
                'Required document removed by staff',
                'Required document removed by staff',
                'Staff removed an uploaded required document: ' . ($step?->name ?: $fileName ?: 'Required document') . '.',
                'Staff removed an uploaded required document.',
                [
                    'document_upload_step_id' => $step?->id,
                    'document_upload_id' => $requestDocumentUpload->id,
                    'document_name' => $step?->name,
                    'file_name' => $fileName,
                ],
            );

            $this->syncAfterDirectDocumentEdit($financeRequest->fresh(), 'required');
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Required document upload removed successfully.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }

    public function storeAdditionalDocument(StoreAdditionalDocumentRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureCanManageClientCorrections($user, $financeRequest);

        $additionalDocument = DB::transaction(function () use ($request, $financeRequest, $user) {
            $additionalDocument = RequestAdditionalDocument::create([
                'finance_request_id' => $financeRequest->id,
                'requested_by' => $user?->id,
                'title' => trim((string) $request->input('title')),
                'reason' => $request->filled('reason') ? trim((string) $request->input('reason')) : null,
                'status' => RequestAdditionalDocumentStatus::PENDING,
                'requested_at' => now(),
            ]);

            $this->moveAfterAdditionalDocumentRequest($financeRequest);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.additional_document_requested',
                $user?->id,
                'Additional document requested',
                'تم طلب مستند إضافي',
                'Staff requested an additional document from the client: ' . $additionalDocument->title . '.',
                'طلب الموظف مستنداً إضافياً من العميل: ' . $additionalDocument->title . '.',
                [
                    'additional_document_id' => $additionalDocument->id,
                    'title' => $additionalDocument->title,
                    'reason' => $additionalDocument->reason,
                ],
            );

            return $additionalDocument->load('requester:id,name,email');
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Additional document request created successfully.',
            'additional_document' => $additionalDocument,
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ], 201);
    }

    public function storeUpdateBatch(StoreFinanceRequestUpdateBatchRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureCanManageClientCorrections($user, $financeRequest);

        DB::transaction(function () use ($request, $financeRequest, $user) {
            $this->updateService->openClientUpdateBatch($financeRequest, $user, $request->validated());
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Client update batch created successfully.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ], 201);
    }

    public function uploadAdditionalDocumentOnBehalf(
        Request $request,
        FinanceRequest $financeRequest,
        RequestAdditionalDocument $additionalDocument,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureCanManageClientCorrections($user, $financeRequest);
        abort_unless((int) $additionalDocument->finance_request_id === (int) $financeRequest->id, 404);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        $status = $additionalDocument->status?->value ?? (string) $additionalDocument->status;
        abort_if($status === RequestAdditionalDocumentStatus::CANCELLED->value, 422, 'This additional document request has been cancelled.');

        $file = $validated['file'];

        DB::transaction(function () use ($financeRequest, $additionalDocument, $file, $user): void {
            if (filled($additionalDocument->file_path)) {
                Storage::disk($additionalDocument->disk ?: 'public')->delete($additionalDocument->file_path);
            }

            $path = $file->store('request-documents/additional', 'public');

            $additionalDocument->update([
                'status' => RequestAdditionalDocumentStatus::UPLOADED,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user?->id,
                'uploaded_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ]);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.additional_document_uploaded_by_staff',
                $user?->id,
                'Additional document uploaded by staff',
                'Additional document uploaded by staff',
                'Staff uploaded an additional document on behalf of the client: ' . $additionalDocument->title . '.',
                'Staff uploaded an additional document on behalf of the client.',
                [
                    'additional_document_id' => $additionalDocument->id,
                    'title' => $additionalDocument->title,
                    'file_name' => $file->getClientOriginalName(),
                ],
            );

            $this->syncAfterDirectDocumentEdit($financeRequest->fresh(), 'additional');
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Additional document uploaded successfully.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }

    public function deleteAdditionalDocumentFile(
        Request $request,
        FinanceRequest $financeRequest,
        RequestAdditionalDocument $additionalDocument,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureCanManageClientCorrections($user, $financeRequest);
        abort_unless((int) $additionalDocument->finance_request_id === (int) $financeRequest->id, 404);
        abort_unless(filled($additionalDocument->file_path), 422, 'This additional document does not have an uploaded file to remove.');

        DB::transaction(function () use ($financeRequest, $additionalDocument, $user): void {
            $fileName = $additionalDocument->file_name;
            Storage::disk($additionalDocument->disk ?: 'public')->delete($additionalDocument->file_path);

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
                'request.additional_document_removed_by_staff',
                $user?->id,
                'Additional document removed by staff',
                'Additional document removed by staff',
                'Staff removed an uploaded additional document: ' . ($additionalDocument->title ?: $fileName ?: 'Additional document') . '.',
                'Staff removed an uploaded additional document.',
                [
                    'additional_document_id' => $additionalDocument->id,
                    'title' => $additionalDocument->title,
                    'file_name' => $fileName,
                ],
            );

            $this->syncAfterDirectDocumentEdit($financeRequest->fresh(), 'additional');
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Additional document file removed successfully.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }

    public function requestAdditionalDocumentChange(
        Request $request,
        FinanceRequest $financeRequest,
        RequestAdditionalDocument $additionalDocument,
    ): JsonResponse {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        $this->ensureCanManageClientCorrections($user, $financeRequest);
        abort_unless((int) $additionalDocument->finance_request_id === (int) $financeRequest->id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $status = $additionalDocument->status?->value ?? (string) $additionalDocument->status;
        abort_if($status === RequestAdditionalDocumentStatus::REJECTED->value, 422, 'A change has already been requested for this additional document.');
        abort_unless(in_array($status, [
            RequestAdditionalDocumentStatus::UPLOADED->value,
            RequestAdditionalDocumentStatus::APPROVED->value,
        ], true), 422, 'The client has not uploaded this additional document yet.');

        $reason = trim((string) $validated['reason']);

        DB::transaction(function () use ($financeRequest, $additionalDocument, $user, $reason) {
            $additionalDocument->update([
                'status' => RequestAdditionalDocumentStatus::REJECTED,
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->moveToClientDocumentCorrectionStage($financeRequest);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.additional_document_change_requested',
                $user?->id,
                'Additional document change requested',
                'Additional document change requested',
                'Staff requested a corrected upload for: ' . $additionalDocument->title . '.',
                'Staff requested a corrected upload for: ' . $additionalDocument->title . '.',
                [
                    'additional_document_id' => $additionalDocument->id,
                    'title' => $additionalDocument->title,
                    'reason' => $reason,
                ],
            );
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'The client can now upload a replacement for this additional document.',
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }

    public function sendEmail(SendFinanceRequestEmailRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        abort_unless($user && ($user->hasRole('admin') || $user->can('send request emails')), 403);

        $requestEmail = $this->requestEmailService->sendToAssignedAgent(
            $financeRequest,
            $user,
            $request->validated(),
        );

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh(), $user);

        return response()->json([
            'message' => 'Request email queued successfully. Delivery will continue in the background.',
            'email' => $requestEmail,
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
            ...$this->agentAssignmentService->emailOptions(
                $freshRequest,
                $request->validated('bank_id'),
                $request->validated('agent_id'),
            ),
        ]);
    }

    public function emailOptions(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        abort_unless($user && ($user->hasRole('admin') || $user->can('send request emails') || $user->can('view assigned requests')), 403);

        $bankId = $request->filled('bank_id') ? (int) $request->input('bank_id') : null;
        $agentId = $request->filled('agent_id') ? (int) $request->input('agent_id') : null;

        return response()->json(
            $this->agentAssignmentService->emailOptions($financeRequest, $bankId, $agentId)
        );
    }

    public function agents(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('view assigned requests')), 403);

        $bankId = $request->filled('bank_id') ? (int) $request->input('bank_id') : null;

        $banks = Bank::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'code']);

        $agents = Agent::query()
            ->with('bank:id,name,short_name,code')
            ->where('is_active', true)
            ->when($bankId, fn ($query) => $query->where('bank_id', $bankId))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'company_name', 'agent_type', 'bank_id'])
            ->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'company_name' => $agent->company_name,
                'agent_type' => $agent->agent_type,
                'bank_id' => $agent->bank_id,
                'bank_name' => $agent->bank?->name,
                'bank_short_name' => $agent->bank?->short_name,
                'bank_code' => $agent->bank?->code,
            ])
            ->values();

        return response()->json([
            'banks' => $banks,
            'agents' => $agents,
        ]);
    }

    private function loadRequestGraph(FinanceRequest $financeRequest, ?User $viewer = null): FinanceRequest
    {
        $viewer ??= Auth::user();

        $financeRequest->load([
            'client:id,name,email,phone',
            'primaryStaff:id,name,email,phone',
            'understudySubmittedBy:id,name,email',
            'understudyReviewedBy:id,name,email',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments.uploader:id,name',
            'currentContract',
            'timeline.actor:id,name',
            'financeRequestType:id,slug,name_en,name_ar,description_en,description_ar,is_active,sort_order',
            'staffQuestions.asker:id,name,email',
            'staffQuestions.assignedStaff:id,name,email',
            'staffQuestions.answerer:id,name,email',
            'staffQuestions.template:id,code,question_text_en,question_text_ar,question_type,finance_type,is_required,is_active,sort_order',
            'updateBatches.requester:id,name,email',
            'updateBatches.items.question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required',
            'updateItems.question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required',
            'agentAssignments.agent:id,name,email,bank_id',
            'agentAssignments.bank:id,name,short_name,code',
            'agentAssignments.assignedBy:id,name,email',
            'agentAssignments.allowedDocuments',
            'shareholders',
            'assignments' => fn ($query) => $query->where('is_active', true)->with(['staff:id,name,email', 'assignedBy:id,name,email'])->orderByDesc('is_primary')->orderBy('assigned_at'),
            'comments' => function ($query) use ($viewer) {
                $query->with('user:id,name,email')->latest();

                if ($viewer instanceof User && ! $viewer->hasRole('admin')) {
                    $query->where('visibility', '!=', RequestCommentVisibility::ADMIN_ONLY->value);
                }
            },
            'additionalDocuments.requester:id,name',
            'additionalDocuments.uploader:id,name',
            'emails' => fn ($query) => $query
                ->with([
                    'sender:id,name,email',
                    'agents.bank:id,name,short_name,code',
                    'attachments',
                ])
                ->latest('id'),
        ]);

        return $financeRequest;
    }

    private function ensureVisibleToUser($user, FinanceRequest $financeRequest): void
    {
        abort_unless($user && ($user->hasRole('admin') || $user->can('view assigned requests')), 403);

        if ($user->hasRole('admin')) {
            return;
        }

        $isAssigned = (int) $financeRequest->primary_staff_id === (int) $user->id
            || $financeRequest->assignments()
                ->where('staff_id', $user->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($isAssigned, 403, 'You are not assigned to this request.');
    }

    private function ensureCanManageClientCorrections(?User $user, FinanceRequest $financeRequest): void
    {
        abort_unless($user && ($user->hasRole('admin') || $this->canRequestClientUpdatesForAssignment($user, $financeRequest)), 403);
    }

    private function canRequestClientUpdatesForAssignment(User $user, FinanceRequest $financeRequest): bool
    {
        return $financeRequest->assignments()
            ->where('staff_id', $user->id)
            ->where('is_active', true)
            ->where('can_request_client_updates', true)
            ->exists();
    }

    private function syncAfterDirectDocumentEdit(FinanceRequest $financeRequest, string $documentKind): void
    {
        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;

        if (in_array($stage, [
            FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
            FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
            FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
        ], true)) {
            if ($documentKind === 'additional') {
                $this->workflowService->syncAfterAdditionalDocuments($financeRequest);
                return;
            }

            $this->workflowService->syncAfterRequiredDocuments($financeRequest);
            return;
        }

        $financeRequest->forceFill([
            'latest_activity_at' => now(),
        ])->save();
    }

    private function moveAfterAdditionalDocumentRequest(FinanceRequest $financeRequest): void
    {
        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;

        if (in_array($stage, [
            FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
            FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
            FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
        ], true)) {
            $this->workflowService->moveToAwaitingAdditionalDocuments($financeRequest);
            return;
        }

        $this->moveToClientDocumentCorrectionStage($financeRequest);
    }

    private function moveToClientDocumentCorrectionStage(FinanceRequest $financeRequest): void
    {
        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;

        if ($stage !== FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value) {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED;
        }

        $financeRequest->status = FinanceRequestStatus::ACTIVE;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();
    }

    private function ensureUnderstudyEditable(FinanceRequest $financeRequest): void
    {
        $understudyStatus = $financeRequest->understudy_status?->value ?? (string) $financeRequest->understudy_status;
        $workflowStage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;

        if (in_array($understudyStatus, [
            FinanceRequestUnderstudyStatus::SUBMITTED->value,
            FinanceRequestUnderstudyStatus::APPROVED->value,
        ], true) || $workflowStage === FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW->value) {
            throw ValidationException::withMessages([
                'understudy' => 'The study package is locked while waiting for admin review. It becomes editable again when the admin requests updates.',
            ]);
        }
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
