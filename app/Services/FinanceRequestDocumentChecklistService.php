<?php

namespace App\Services;

use App\Enums\RequestAdditionalDocumentStatus;
use App\Enums\RequestDocumentUploadStatus;
use App\Models\DocumentUploadStep;
use App\Models\FinanceRequest;
use Illuminate\Support\Collection;

class FinanceRequestDocumentChecklistService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildRequiredChecklist(FinanceRequest $financeRequest): Collection
    {
        $requiredSteps = DocumentUploadStep::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $uploadsByStep = $financeRequest->documentUploads()
            ->with('documentUploadStep:id,name,code,is_required,is_multiple')
            ->whereIn('document_upload_step_id', $requiredSteps->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('document_upload_step_id');

        $latestUploadsByStep = $uploadsByStep
            ->map(fn (Collection $items) => $items->first());

        return $requiredSteps->map(function (DocumentUploadStep $step) use ($latestUploadsByStep, $uploadsByStep) {
            $latestUpload = $latestUploadsByStep->get($step->id);
            $status = $latestUpload?->status?->value ?? (string) ($latestUpload?->status ?? 'pending');
            $isRejected = $status === RequestDocumentUploadStatus::REJECTED->value;
            $stepUploads = $uploadsByStep->get($step->id, collect());

            $acceptedUploadsCount = $stepUploads
                ->filter(function ($upload): bool {
                    $uploadStatus = $upload->status?->value ?? (string) ($upload->status ?? '');

                    return $uploadStatus !== RequestDocumentUploadStatus::REJECTED->value;
                })
                ->count();
            $isSatisfied = $acceptedUploadsCount > 0;
            $isMultiple = (bool) $step->is_multiple;

            return [
                'document_upload_step_id' => $step->id,
                'code' => $step->code,
                'name' => $step->name,
                'is_required' => true,
                'is_multiple' => $isMultiple,
                'status' => $status,
                'is_uploaded' => $isSatisfied,
                'can_client_upload' => $isMultiple || $latestUpload === null || $isRejected,
                'is_change_requested' => $isRejected,
                'rejection_reason' => $latestUpload?->rejection_reason,
                'uploads_count' => $stepUploads->count(),
                'accepted_uploads_count' => $acceptedUploadsCount,
                'upload' => $latestUpload,
                'uploads' => $stepUploads->values(),
            ];
        });
    }

    public function hasMissingRequiredDocuments(FinanceRequest $financeRequest): bool
    {
        return $this->buildRequiredChecklist($financeRequest)
            ->contains(fn (array $item) => ! $item['is_uploaded']);
    }

    public function hasPendingAdditionalDocumentRequests(FinanceRequest $financeRequest): bool
    {
        return $financeRequest->additionalDocuments()
            ->whereIn('status', [
                RequestAdditionalDocumentStatus::PENDING->value,
                RequestAdditionalDocumentStatus::REJECTED->value,
            ])
            ->exists();
    }
}
