<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewFinanceRequestUpdateItemRequest;
use App\Http\Requests\Admin\StoreFinanceRequestUpdateBatchRequest;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestUpdateBatch;
use App\Models\FinanceRequestUpdateItem;
use App\Services\FinanceRequestDocumentChecklistService;
use App\Services\FinanceRequestStaffQuestionService;
use App\Services\FinanceRequestUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FinanceRequestUpdateBatchController extends Controller
{
    public function __construct(
        private readonly FinanceRequestUpdateService $updateService,
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
        private readonly FinanceRequestStaffQuestionService $staffQuestionService,
    ) {
    }

    public function store(StoreFinanceRequestUpdateBatchRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $actor = $request->user();

        DB::transaction(function () use ($request, $financeRequest, $actor) {
            $this->updateService->openClientUpdateBatch($financeRequest, $actor, $request->validated());
        });

        $financeRequest = $financeRequest->fresh();

        return response()->json([
            'message' => 'Client update batch created successfully.',
            'request' => app(AdminFinanceRequestController::class)->show($financeRequest)->getData(true)['request'],
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($financeRequest->loadMissing('staffQuestions')),
        ], 201);
    }


    public function cancel(FinanceRequest $financeRequest, FinanceRequestUpdateBatch $updateBatch): JsonResponse
    {
        $actor = request()->user();

        DB::transaction(function () use ($financeRequest, $updateBatch, $actor) {
            $this->updateService->cancelClientUpdateBatch($financeRequest, $updateBatch, $actor);
        });

        $financeRequest = $financeRequest->fresh();

        return response()->json([
            'message' => 'Client update batch cancelled successfully.',
            'request' => app(AdminFinanceRequestController::class)->show($financeRequest)->getData(true)['request'],
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($financeRequest->loadMissing('staffQuestions')),
        ]);
    }

    public function review(
        ReviewFinanceRequestUpdateItemRequest $request,
        FinanceRequest $financeRequest,
        FinanceRequestUpdateItem $updateItem,
    ): JsonResponse {
        $actor = $request->user();

        DB::transaction(function () use ($request, $financeRequest, $updateItem, $actor) {
            $this->updateService->reviewClientUpdateItem(
                $financeRequest,
                $updateItem,
                $actor,
                (string) $request->validated('action'),
                $request->validated('review_note'),
            );
        });

        $financeRequest = $financeRequest->fresh();

        return response()->json([
            'message' => 'Update item reviewed successfully.',
            'request' => app(AdminFinanceRequestController::class)->show($financeRequest)->getData(true)['request'],
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
            'staff_question_summary' => $this->staffQuestionService->summary($financeRequest->loadMissing('staffQuestions')),
        ]);
    }
}
