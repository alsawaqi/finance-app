<?php

namespace App\Services;

use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Models\FinanceRequest;

class FinanceRequestWorkflowService
{
    public function __construct(
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
    ) {
    }

    public function moveToDocumentCollection(FinanceRequest $financeRequest): FinanceRequest
    {
        $financeRequest->status = FinanceRequestStatus::ACTIVE;
        $financeRequest->workflow_stage = FinanceRequestWorkflowStage::DOCUMENT_COLLECTION;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        return $financeRequest;
    }

    public function syncAfterRequiredDocuments(FinanceRequest $financeRequest): FinanceRequest
    {
        if ($this->documentChecklistService->hasMissingRequiredDocuments($financeRequest)) {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::DOCUMENT_COLLECTION;
        } elseif ($this->documentChecklistService->hasPendingAdditionalDocumentRequests($financeRequest)) {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS;
        } else {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::PROCESSING;
        }

        $financeRequest->status = FinanceRequestStatus::ACTIVE;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        return $financeRequest;
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
        } elseif ($this->documentChecklistService->hasMissingRequiredDocuments($financeRequest)) {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::DOCUMENT_COLLECTION;
        } else {
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::PROCESSING;
        }

        $financeRequest->status = FinanceRequestStatus::ACTIVE;
        $financeRequest->latest_activity_at = now();
        $financeRequest->save();

        return $financeRequest;
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
