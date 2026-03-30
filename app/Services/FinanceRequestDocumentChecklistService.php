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

        $latestUploadsByStep = $financeRequest->documentUploads()
            ->with('documentUploadStep:id,name,code,is_required')
            ->whereIn('document_upload_step_id', $requiredSteps->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('document_upload_step_id')
            ->map(fn (Collection $items) => $items->first());

        return $requiredSteps->map(function (DocumentUploadStep $step) use ($latestUploadsByStep) {
            $latestUpload = $latestUploadsByStep->get($step->id);
            $status = $latestUpload?->status?->value ?? (string) ($latestUpload?->status ?? 'pending');
            $isSatisfied = $latestUpload !== null && $status !== RequestDocumentUploadStatus::REJECTED->value;

            return [
                'document_upload_step_id' => $step->id,
                'code' => $step->code,
                'name' => $step->name,
                'is_required' => true,
                'status' => $isSatisfied ? 'uploaded' : 'pending',
                'is_uploaded' => $isSatisfied,
                'upload' => $latestUpload,
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
