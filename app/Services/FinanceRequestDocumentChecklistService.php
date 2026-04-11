<?php

namespace App\Services;

use App\Enums\RequestAdditionalDocumentStatus;
use App\Enums\RequestDocumentUploadStatus;
use App\Models\DocumentUploadStep;
use App\Models\FinanceRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

class FinanceRequestDocumentChecklistService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildRequiredChecklist(FinanceRequest $financeRequest): Collection
    {
        $requestFinanceType = $this->resolveRequestFinanceType($financeRequest);

        $requiredSteps = DocumentUploadStep::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->where(function ($query) use ($requestFinanceType): void {
                $query
                    ->whereNull('finance_type')
                    ->orWhere('finance_type', 'all')
                    ->orWhere('finance_type', $requestFinanceType);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $uploadsByStep = $financeRequest->documentUploads()
            ->with('documentUploadStep:id,name,code,finance_type,is_required,is_multiple')
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
                'finance_type' => (string) ($step->finance_type ?: 'all'),
                'is_required' => true,
                'is_multiple' => $isMultiple,
                'allowed_file_types' => collect((array) ($step->allowed_file_types_json ?? []))
                    ->map(fn ($type) => strtolower(trim((string) $type)))
                    ->filter(fn (string $type) => $type !== '')
                    ->values()
                    ->all(),
                'max_file_size_mb' => $step->max_file_size_mb !== null ? (int) $step->max_file_size_mb : null,
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

    public function resolveRequestFinanceType(FinanceRequest $financeRequest): string
    {
        $applicantType = strtolower(trim((string) (
            $financeRequest->applicant_type
            ?: Arr::get($financeRequest->intake_details_json ?? [], 'finance_type', 'individual')
        )));

        return in_array($applicantType, ['individual', 'company'], true)
            ? $applicantType
            : 'individual';
    }

    public function isStepApplicableForRequest(FinanceRequest $financeRequest, ?DocumentUploadStep $documentUploadStep): bool
    {
        if (! $documentUploadStep) {
            return false;
        }

        $stepFinanceType = strtolower(trim((string) ($documentUploadStep->finance_type ?: 'all')));
        if ($stepFinanceType === '' || $stepFinanceType === 'all') {
            return true;
        }

        return $stepFinanceType === $this->resolveRequestFinanceType($financeRequest);
    }

    public function findRequiredStepForRequest(FinanceRequest $financeRequest, int $stepId): ?DocumentUploadStep
    {
        $requestFinanceType = $this->resolveRequestFinanceType($financeRequest);

        return DocumentUploadStep::query()
            ->whereKey($stepId)
            ->where('is_active', true)
            ->where('is_required', true)
            ->where(function ($query) use ($requestFinanceType): void {
                $query
                    ->whereNull('finance_type')
                    ->orWhere('finance_type', 'all')
                    ->orWhere('finance_type', $requestFinanceType);
            })
            ->first();
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
