<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFinanceRequestAgentAssignmentsRequest;
use App\Models\FinanceRequest;
use App\Services\FinanceRequestAgentAssignmentService;
use App\Services\FinanceRequestDocumentChecklistService;
use App\Services\FinanceRequestStaffQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FinanceRequestAgentAssignmentController extends Controller
{
    public function __construct(
        private readonly FinanceRequestAgentAssignmentService $agentAssignmentService,
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
        private readonly FinanceRequestStaffQuestionService $staffQuestionService,
    ) {
    }

    public function options(FinanceRequest $financeRequest): JsonResponse
    {
        return response()->json($this->agentAssignmentService->options($financeRequest));
    }

    public function store(StoreFinanceRequestAgentAssignmentsRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $updatedRequest = DB::transaction(function () use ($request, $financeRequest) {
            return $this->agentAssignmentService->assignAgents(
                $financeRequest,
                $request->validated('assignments'),
                $request->user()?->id,
                $request->validated('review_note'),
            );
        });

        $updatedRequest->load([
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
            'shareholders',
            'additionalDocuments.requester:id,name',
            'additionalDocuments.uploader:id,name',
            'assignments.staff:id,name,email',
            'comments.user:id,name',
            'emails' => fn ($query) => $query
                ->with([
                    'sender:id,name,email',
                    'agents.bank:id,name,short_name,code',
                    'attachments',
                ])
                ->latest('id'),
        ]);

        return response()->json([
            'message' => 'Allowed bank agents and linked request documents saved successfully.',
            'request' => $updatedRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($updatedRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($updatedRequest),
            ...$this->agentAssignmentService->options($updatedRequest),
        ]);
    }
}
