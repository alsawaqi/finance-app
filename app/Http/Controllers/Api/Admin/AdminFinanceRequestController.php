<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdvanceFinanceRequestFromUnderstudyRequest;
use App\Http\Requests\Admin\ApproveFinanceRequestRequest;
use App\Http\Requests\Admin\FinalApproveFinanceRequestRequest;
use App\Http\Requests\Admin\RejectFinanceRequestRequest;
use App\Http\Requests\Admin\ReviewFinanceRequestUnderstudyRequest;
use App\Http\Requests\Admin\ReviewFinanceRequestStaffQuestionRequest;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestStaffQuestion;
use App\Services\FinanceRequestDocumentChecklistService;
use App\Services\FinanceRequestStaffQuestionService;
use App\Services\FinanceRequestWorkflowService;
use App\Support\RequestTimelineLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminFinanceRequestController extends Controller
{
    public function __construct(
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
        private readonly FinanceRequestStaffQuestionService $staffQuestionService,
        private readonly FinanceRequestWorkflowService $workflowService,
    ) {
    }

    public function indexNew(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'queue' => ['nullable', 'in:all,pending,contract'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $queue = $validated['queue'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? 12);

        $pendingStages = [
            FinanceRequestWorkflowStage::QUESTIONNAIRE->value,
            FinanceRequestWorkflowStage::REVIEW->value,
            FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
        ];

        $contractStages = [
            FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
            FinanceRequestWorkflowStage::CONTRACT->value,
            FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
            FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
            FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value,
        ];

        $baseQuery = FinanceRequest::query()
            ->with([
                'client:id,name,email',
                'currentContract:id,finance_request_id,status,client_signed_at',
                'financeRequestType:id,slug,name_en,name_ar',
            ])
            ->where(function ($query) use ($pendingStages, $contractStages) {
                $query
                    ->where(function ($inner) use ($pendingStages) {
                        $inner
                            ->where('status', FinanceRequestStatus::SUBMITTED->value)
                            ->whereIn('workflow_stage', $pendingStages);
                    })
                    ->orWhere(function ($inner) use ($contractStages) {
                        $inner
                            ->where('status', FinanceRequestStatus::ACTIVE->value)
                            ->whereIn('workflow_stage', $contractStages);
                    });
            });

        if ($queue === 'pending') {
            $baseQuery
                ->where('status', FinanceRequestStatus::SUBMITTED->value)
                ->whereIn('workflow_stage', $pendingStages);
        }

        if ($queue === 'contract') {
            $baseQuery
                ->where('status', FinanceRequestStatus::ACTIVE->value)
                ->whereIn('workflow_stage', $contractStages);
        }

        $requestsPaginator = $baseQuery
            ->orderByRaw(
                "CASE
                    WHEN workflow_stage = ? THEN 0
                    WHEN workflow_stage = ? THEN 1
                    WHEN workflow_stage = ? THEN 2
                    WHEN workflow_stage = ? THEN 3
                    WHEN workflow_stage = ? THEN 4
                    WHEN workflow_stage = ? THEN 5
                    WHEN workflow_stage = ? THEN 6
                    WHEN workflow_stage = ? THEN 7
                    ELSE 8
                END",
                [
                    FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
                    FinanceRequestWorkflowStage::REVIEW->value,
                    FinanceRequestWorkflowStage::QUESTIONNAIRE->value,
                    FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
                    FinanceRequestWorkflowStage::CONTRACT->value,
                    FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
                    FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
                    FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value,
                ]
            )
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $requests = collect($requestsPaginator->items())->values();

        $pendingCount = FinanceRequest::query()
            ->where('status', FinanceRequestStatus::SUBMITTED->value)
            ->whereIn('workflow_stage', $pendingStages)
            ->count();

        $contractCount = FinanceRequest::query()
            ->where('status', FinanceRequestStatus::ACTIVE->value)
            ->whereIn('workflow_stage', $contractStages)
            ->count();

        return response()->json([
            'selected_queue' => $queue,
            'queue_summary' => [
                'all' => $pendingCount + $contractCount,
                'pending' => $pendingCount,
                'contract' => $contractCount,
            ],
            'requests' => $requests,
            'pagination' => $this->paginationMeta($requestsPaginator),
        ]);
    }

    public function show(FinanceRequest $financeRequest): JsonResponse
    {
        $financeRequest = $this->loadRequestGraph($financeRequest);

        return response()->json([
            'request' => $financeRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($financeRequest),
        ]);
    }

    public function approve(ApproveFinanceRequestRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $admin = $request->user();

        DB::transaction(function () use ($financeRequest, $admin, $request) {
            if (blank($financeRequest->approval_reference_number)) {
                $financeRequest->approval_reference_number = 'APR-' . now()->format('Y') . '-' . str_pad((string) $financeRequest->id, 6, '0', STR_PAD_LEFT);
            }

            $financeRequest->status = FinanceRequestStatus::ACTIVE;
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION;
            $financeRequest->approved_at = $financeRequest->approved_at ?: now();
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.approved',
                $admin?->id,
                'Request approved for contract creation',
                'تمت الموافقة على الطلب لإعداد العقد',
                $request->input('approval_notes') ?: 'The request was reviewed and approved. Contract drafting can now begin.',
                'تمت مراجعة الطلب والموافقة عليه ويمكن الآن البدء في إعداد العقد.',
                [
                    'approval_reference_number' => $financeRequest->approval_reference_number,
                ],
            );
        });

        return response()->json([
            'message' => 'Request approved successfully.',
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract']),
        ]);
    }

    public function finalApprove(FinalApproveFinanceRequestRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $admin = $request->user();
        $status = $financeRequest->status?->value ?? (string) $financeRequest->status;
        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;

        if (in_array($status, [
            FinanceRequestStatus::REJECTED->value,
            FinanceRequestStatus::COMPLETED->value,
            FinanceRequestStatus::CANCELLED->value,
        ], true)) {
            return response()->json([
                'message' => 'This request cannot be finalized from its current status.',
            ], 422);
        }

        if (! in_array($stage, [
            FinanceRequestWorkflowStage::PROCESSING->value,
            FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
        ], true)) {
            return response()->json([
                'message' => 'Final approval is available only at the final processing stage.',
            ], 422);
        }

        DB::transaction(function () use ($financeRequest, $admin, $request) {
            $this->workflowService->markCompleted($financeRequest);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.final_approved',
                $admin?->id,
                'Request finally approved and completed',
                'تمت الموافقة النهائية على الطلب واكتماله',
                $request->validated('final_approval_notes') ?: 'The admin finalized the request approval and marked it as completed.',
                $request->validated('final_approval_notes') ?: 'قام المسؤول باعتماد الطلب نهائياً وتم تحويله إلى حالة مكتمل.',
                [
                    'status' => FinanceRequestStatus::COMPLETED->value,
                    'workflow_stage' => FinanceRequestWorkflowStage::COMPLETED->value,
                    'completed_at' => optional($financeRequest->completed_at)->toISOString(),
                ],
            );
        });

        $financeRequest = $this->loadRequestGraph($financeRequest->fresh());

        return response()->json([
            'message' => 'Request finalized successfully.',
            'request' => $financeRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($financeRequest),
        ]);
    }

    public function reject(RejectFinanceRequestRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $admin = $request->user();

        DB::transaction(function () use ($financeRequest, $admin, $request) {
            $financeRequest->status = FinanceRequestStatus::REJECTED;
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::REJECTED;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.rejected',
                $admin?->id,
                'Request rejected',
                'تم رفض الطلب',
                $request->validated('reason') ?: 'The admin rejected the request.',
                $request->validated('reason') ?: 'قام المسؤول برفض الطلب.',
                [
                    'status' => FinanceRequestStatus::REJECTED->value,
                    'workflow_stage' => FinanceRequestWorkflowStage::REJECTED->value,
                ],
            );
        });

        $financeRequest = $this->loadRequestGraph($financeRequest->fresh());

        return response()->json([
            'message' => 'Request rejected successfully.',
            'request' => $financeRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($financeRequest),
        ]);
    }

    public function reviewStaffQuestion(
        ReviewFinanceRequestStaffQuestionRequest $request,
        FinanceRequest $financeRequest,
        FinanceRequestStaffQuestion $staffQuestion,
    ): JsonResponse {
        $admin = $request->user();

        $reviewedQuestion = DB::transaction(function () use ($request, $financeRequest, $staffQuestion, $admin) {
            $reviewedQuestion = $this->staffQuestionService->reviewQuestion(
                $financeRequest,
                $staffQuestion,
                $admin,
                (string) $request->validated('action'),
                $request->validated('review_note'),
            );

            $this->workflowService->syncStaffQuestionStage($financeRequest, $admin?->id);

            return $reviewedQuestion;
        });

        $freshRequest = $this->loadRequestGraph($financeRequest->fresh());

        return response()->json([
            'message' => 'Staff study question reviewed successfully.',
            'staff_question' => $reviewedQuestion,
            'request' => $freshRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($freshRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($freshRequest),
        ]);
    }


    public function reviewUnderstudy(
        ReviewFinanceRequestUnderstudyRequest $request,
        FinanceRequest $financeRequest,
    ): JsonResponse {
        $admin = $request->user();

        $reviewedRequest = DB::transaction(function () use ($financeRequest, $admin, $request) {
            return $this->staffQuestionService->reviewStudy(
                $financeRequest,
                $admin,
                (string) $request->validated('action'),
                $request->validated('review_note'),
            );
        });

        $reviewedRequest = $this->loadRequestGraph($reviewedRequest);

        return response()->json([
            'message' => 'Understudy review completed successfully.',
            'request' => $reviewedRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($reviewedRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($reviewedRequest),
        ]);
    }

    public function advanceFromUnderstudy(
        AdvanceFinanceRequestFromUnderstudyRequest $request,
        FinanceRequest $financeRequest,
    ): JsonResponse {
        $admin = $request->user();

        $advancedRequest = DB::transaction(function () use ($financeRequest, $admin, $request) {
            return $this->workflowService->advanceFromUnderstudy(
                $financeRequest,
                $admin?->id,
                $request->validated('review_note'),
            );
        });

        $advancedRequest = $this->loadRequestGraph($advancedRequest);

        return response()->json([
            'message' => 'The request has passed the understudy review and is now ready for agent assignment.',
            'request' => $advancedRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($advancedRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($advancedRequest),
        ]);
    }

    private function loadRequestGraph(FinanceRequest $financeRequest): FinanceRequest
    {
        $financeRequest->load([
            'client:id,name,email,phone',
            'primaryStaff:id,name,email,phone',
            'understudySubmittedBy:id,name,email',
            'understudyReviewedBy:id,name,email',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments.uploader:id,name',
            'timeline.actor:id,name',
            'financeRequestType:id,slug,name_en,name_ar,description_en,description_ar,is_active,sort_order',
            'staffQuestions.asker:id,name,email',
            'staffQuestions.assignedStaff:id,name,email',
            'staffQuestions.template:id,code,question_text_en,question_text_ar,question_type,is_required,is_active,sort_order',
            'updateBatches.requester:id,name,email',
            'updateBatches.items.question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required',
            'updateItems.question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required',
            'agentAssignments.agent:id,name,email,bank_id',
            'agentAssignments.bank:id,name,short_name,code',
            'agentAssignments.assignedBy:id,name,email',
            'agentAssignments.allowedDocuments',
            'currentContract',
            'contracts',
            'shareholders',
            'documentUploads.step:id,code,name,description,is_required,is_multiple,allowed_file_types_json,max_file_size_mb',
            'documentUploads.uploader:id,name,email',
            'documentUploads.reviewer:id,name,email',
            'additionalDocuments.requester:id,name,email',
            'additionalDocuments.uploader:id,name,email',
            'assignments' => fn ($query) => $query->with(['staff:id,name,email,phone', 'assignedBy:id,name,email'])->orderByDesc('is_primary')->orderBy('assigned_at'),
            'comments' => fn ($query) => $query->with('user:id,name,email')->latest('created_at'),
            'emails' => fn ($query) => $query->with([
                'sender:id,name,email',
                'agents.bank:id,name,short_name,code',
                'attachments',
            ])->latest('created_at'),
        ]);

        return $financeRequest;
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
