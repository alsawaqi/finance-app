<?php

namespace App\Services;

use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestUnderstudyStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestUpdateBatch;
use App\Support\RequestTimelineLogger;
use Illuminate\Validation\ValidationException;

class FinanceRequestWorkflowService
{
    public function __construct(
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
        private readonly FinanceRequestStaffQuestionTemplateService $staffQuestionTemplateService,
        private readonly FinanceRequestStaffQuestionService $staffQuestionService,
    ) {
    }

    public function moveToDocumentCollection(FinanceRequest $financeRequest): FinanceRequest
    {
        $financeRequest->status = FinanceRequestStatus::ACTIVE;
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        return $financeRequest;
    }

    public function syncAfterRequiredDocuments(FinanceRequest $financeRequest): FinanceRequest
    {
        if ($this->documentChecklistService->hasMissingRequiredDocuments($financeRequest)) {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS;
            $financeRequest->status = FinanceRequestStatus::ACTIVE;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            return $financeRequest;
        }

        if ($this->documentChecklistService->hasPendingAdditionalDocumentRequests($financeRequest)) {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS;
            $financeRequest->status = FinanceRequestStatus::ACTIVE;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            return $financeRequest;
        }

        return $this->moveToUnderstudy($financeRequest);
    }

    public function moveToAwaitingAdditionalDocuments(FinanceRequest $financeRequest): FinanceRequest
    {
        $financeRequest->status = FinanceRequestStatus::ACTIVE;
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        return $financeRequest;
    }

    public function syncAfterAdditionalDocuments(FinanceRequest $financeRequest): FinanceRequest
    {
        if ($this->documentChecklistService->hasPendingAdditionalDocumentRequests($financeRequest)) {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS;
            $financeRequest->status = FinanceRequestStatus::ACTIVE;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            return $financeRequest;
        }

        if ($this->documentChecklistService->hasMissingRequiredDocuments($financeRequest)) {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS;
            $financeRequest->status = FinanceRequestStatus::ACTIVE;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            return $financeRequest;
        }

        return $this->moveToUnderstudy($financeRequest);
    }

    public function moveToUnderstudy(FinanceRequest $financeRequest, ?int $actorUserId = null): FinanceRequest
    {
        $financeRequest->status = FinanceRequestStatus::ACTIVE;
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::UNDERSTUDY;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        $this->staffQuestionTemplateService->ensureForRequest($financeRequest, $actorUserId);

        return $this->syncStaffQuestionStage($financeRequest->fresh(), $actorUserId);
    }

    public function syncStaffQuestionStage(FinanceRequest $financeRequest, ?int $actorUserId = null): FinanceRequest
    {
        if ($financeRequest->understudy_status === FinanceRequestUnderstudyStatus::SUBMITTED
            && $financeRequest->workflow_stage === FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW) {
            return $financeRequest->fresh();
        }

        $pendingRequired = $this->staffQuestionService->pendingRequiredCount($financeRequest);

        $targetStage = $pendingRequired > 0
            ? FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS
            : FinanceRequestWorkflowStage::UNDERSTUDY;

        if ($financeRequest->workflow_stage !== $targetStage) {
            $financeRequest->workflow_stage = $targetStage;
            $financeRequest->status = FinanceRequestStatus::ACTIVE;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            if ($targetStage === FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS) {
                RequestTimelineLogger::log(
                    $financeRequest,
                    'staff_questions.awaiting_answers',
                    $actorUserId,
                    'Waiting for staff study answers',
                    'بانتظار إجابات الموظف على أسئلة الدراسة',
                    'The request is waiting for the staff member to answer all required study questions.',
                    'الطلب بانتظار أن يجيب الموظف على جميع أسئلة الدراسة المطلوبة.',
                    [
                        'pending_required_total' => $pendingRequired,
                    ],
                );
            }
        }

        return $financeRequest->fresh();
    }

    public function advanceFromUnderstudy(
        FinanceRequest $financeRequest,
        ?int $actorUserId = null,
        ?string $reviewNote = null,
    ): FinanceRequest {
        $financeRequest = $this->syncStaffQuestionStage($financeRequest, $actorUserId);

        $pendingRequired = $this->staffQuestionService->pendingRequiredCount($financeRequest);

        if ($pendingRequired > 0) {
            throw ValidationException::withMessages([
                'staff_questions' => 'All required staff study questions must be answered before moving this request forward.',
            ]);
        }

        $financeRequest->status = FinanceRequestStatus::ACTIVE;
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        RequestTimelineLogger::log(
            $financeRequest,
            'understudy.completed',
            $actorUserId,
            'Understudy completed and ready for agent assignment',
            'اكتملت مرحلة الدراسة وأصبح الطلب جاهزاً لتعيين الوكلاء',
            $reviewNote ?: 'The admin completed the understudy review and moved the request to the next stage.',
            $reviewNote ?: 'أكمل المسؤول مراجعة مرحلة الدراسة ونقل الطلب إلى المرحلة التالية.',
            [
                'next_workflow_stage' => FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
            ],
        );

        return $financeRequest->fresh();
    }


    public function moveToClientUpdateRequested(
        FinanceRequest $financeRequest,
        FinanceRequestUpdateBatch $batch,
        ?int $actorUserId = null,
    ): FinanceRequest {
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        RequestTimelineLogger::log(
            $financeRequest,
            'request.client_update_requested',
            $actorUserId,
            'Client update requested',
            'تم طلب تحديث من العميل',
            'The request was moved to a client update stage for a targeted correction batch.',
            'تم نقل الطلب إلى مرحلة تحديث العميل بسبب دفعة تصحيحات محددة.',
            [
                'update_batch_id' => $batch->id,
                'return_status' => $batch->return_status,
                'return_workflow_stage' => $batch->return_workflow_stage,
            ],
        );

        return $financeRequest->fresh();
    }

    public function restoreAfterClientUpdateBatch(
        FinanceRequest $financeRequest,
        FinanceRequestUpdateBatch $batch,
        ?int $actorUserId = null,
    ): FinanceRequest {
        $returnStatus = $batch->return_status ?: ($financeRequest->status?->value ?? (string) $financeRequest->status);
        $returnWorkflowStage = $batch->return_workflow_stage ?: FinanceRequestWorkflowStage::UNDERSTUDY->value;

        $financeRequest->status = FinanceRequestStatus::from($returnStatus);
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::from($returnWorkflowStage);
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        RequestTimelineLogger::log(
            $financeRequest,
            'request.client_update_completed',
            $actorUserId,
            'Client update batch completed',
            'اكتملت دفعة تحديث العميل',
            'All requested client updates were approved and the request returned to its prior workflow stage.',
            'تم اعتماد جميع تحديثات العميل المطلوبة وعاد الطلب إلى مرحلته السابقة.',
            [
                'update_batch_id' => $batch->id,
                'restored_status' => $returnStatus,
                'restored_workflow_stage' => $returnWorkflowStage,
            ],
        );

        return $financeRequest->fresh();
    }


    public function restoreAfterClientUpdateBatchCancellation(
        FinanceRequest $financeRequest,
        FinanceRequestUpdateBatch $batch,
        ?int $actorUserId = null,
    ): FinanceRequest {
        $returnStatus = $batch->return_status ?: ($financeRequest->status?->value ?? (string) $financeRequest->status);
        $returnWorkflowStage = $batch->return_workflow_stage ?: FinanceRequestWorkflowStage::UNDERSTUDY->value;

        $financeRequest->status = FinanceRequestStatus::from($returnStatus);
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::from($returnWorkflowStage);
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        RequestTimelineLogger::log(
            $financeRequest,
            'request.client_update_cancelled',
            $actorUserId,
            'Client update batch cancelled',
            'تم إلغاء دفعة تحديث العميل',
            'The client update batch was cancelled and the request returned to its prior workflow stage.',
            'تم إلغاء دفعة تحديث العميل وعاد الطلب إلى مرحلته السابقة.',
            [
                'update_batch_id' => $batch->id,
                'restored_status' => $returnStatus,
                'restored_workflow_stage' => $returnWorkflowStage,
            ],
        );

        return $financeRequest->fresh();
    }

    public function markCompleted(FinanceRequest $financeRequest): FinanceRequest
    {
        $financeRequest->status = FinanceRequestStatus::COMPLETED;
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::COMPLETED;
        $financeRequest->completed_at = $financeRequest->completed_at ?: now();
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        return $financeRequest;
    }
}