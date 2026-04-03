<?php

namespace App\Services;

use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Models\Agent;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestAgentAssignment;
use App\Models\FinanceRequestAgentAssignmentDocument;
use App\Models\RequestAdditionalDocument;
use App\Models\RequestAttachment;
use App\Models\RequestDocumentUpload;
use App\Support\RequestTimelineLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceRequestAgentAssignmentService
{
    public function buildAvailableDocuments(FinanceRequest $financeRequest): Collection
    {
        $financeRequest->loadMissing([
            'attachments',
            'shareholders',
            'currentContract',
            'documentUploads.documentUploadStep:id,name,code',
            'additionalDocuments',
        ]);

        $documents = collect();
        $sortOrder = 0;

        foreach ($financeRequest->attachments as $attachment) {
            if (! filled($attachment->file_path)) {
                continue;
            }

            $label = $attachment->category
                ? $this->headline($attachment->category)
                : ($attachment->file_name ?: 'Request attachment');

            $documents->push([
                'key' => $this->documentKey('request_attachment', $attachment->id),
                'document_type' => 'request_attachment',
                'document_id' => (int) $attachment->id,
                'group_label' => 'Request attachments',
                'label' => $label,
                'file_name' => $attachment->file_name,
                'file_path' => $attachment->file_path,
                'disk' => $attachment->disk ?: 'public',
                'mime_type' => $attachment->mime_type,
                'file_extension' => $attachment->file_extension,
                'file_size' => $attachment->file_size,
                'sort_order' => ++$sortOrder,
                'download_url' => "/api/admin/requests/{$financeRequest->id}/attachments/{$attachment->id}/download",
            ]);
        }

        $latestRequiredUploads = $financeRequest->documentUploads
            ->filter(fn (RequestDocumentUpload $upload) => filled($upload->file_path))
            ->sortByDesc('id')
            ->groupBy('document_upload_step_id')
            ->map(fn (Collection $group) => $group->first());

        foreach ($latestRequiredUploads as $upload) {
            $documents->push([
                'key' => $this->documentKey('required_document', $upload->id),
                'document_type' => 'required_document',
                'document_id' => (int) $upload->id,
                'group_label' => 'Required documents',
                'label' => $upload->documentUploadStep?->name ?: ($upload->file_name ?: 'Required document'),
                'file_name' => $upload->file_name,
                'file_path' => $upload->file_path,
                'disk' => $upload->disk ?: 'public',
                'mime_type' => $upload->mime_type,
                'file_extension' => $upload->file_extension,
                'file_size' => $upload->file_size,
                'sort_order' => ++$sortOrder,
                'download_url' => "/api/admin/requests/{$financeRequest->id}/required-documents/{$upload->id}/download",
            ]);
        }

        foreach ($financeRequest->additionalDocuments as $additionalDocument) {
            if (! filled($additionalDocument->file_path)) {
                continue;
            }

            $documents->push([
                'key' => $this->documentKey('additional_document', $additionalDocument->id),
                'document_type' => 'additional_document',
                'document_id' => (int) $additionalDocument->id,
                'group_label' => 'Additional documents',
                'label' => $additionalDocument->title ?: ($additionalDocument->file_name ?: 'Additional document'),
                'file_name' => $additionalDocument->file_name ?: ($additionalDocument->title ?: 'additional-document'),
                'file_path' => $additionalDocument->file_path,
                'disk' => $additionalDocument->disk ?: 'public',
                'mime_type' => $additionalDocument->mime_type,
                'file_extension' => $additionalDocument->file_extension,
                'file_size' => $additionalDocument->file_size,
                'sort_order' => ++$sortOrder,
                'download_url' => "/api/admin/requests/{$financeRequest->id}/additional-documents/{$additionalDocument->id}/download",
            ]);
        }

        foreach ($financeRequest->shareholders as $shareholder) {
            if (! filled($shareholder->id_file_path)) {
                continue;
            }

            $documents->push([
                'key' => $this->documentKey('shareholder_id', $shareholder->id),
                'document_type' => 'shareholder_id',
                'document_id' => (int) $shareholder->id,
                'group_label' => 'Shareholder IDs',
                'label' => 'Shareholder ID · ' . ($shareholder->shareholder_name ?: 'Shareholder'),
                'file_name' => $shareholder->id_file_name,
                'file_path' => $shareholder->id_file_path,
                'disk' => $shareholder->disk ?: 'public',
                'mime_type' => $shareholder->mime_type,
                'file_extension' => $shareholder->file_extension,
                'file_size' => $shareholder->file_size,
                'sort_order' => ++$sortOrder,
                'download_url' => "/api/admin/requests/{$financeRequest->id}/shareholders/{$shareholder->id}/id-file/download",
            ]);
        }

        if (filled($financeRequest->currentContract?->contract_pdf_path)) {
            $contract = $financeRequest->currentContract;

            $documents->push([
                'key' => $this->documentKey('contract_pdf', $contract->id),
                'document_type' => 'contract_pdf',
                'document_id' => (int) $contract->id,
                'group_label' => 'Contracts',
                'label' => 'Signed contract' . ($contract->version_no ? ' · v' . $contract->version_no : ''),
                'file_name' => basename((string) $contract->contract_pdf_path),
                'file_path' => $contract->contract_pdf_path,
                'disk' => 'public',
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'file_size' => null,
                'sort_order' => ++$sortOrder,
                'download_url' => "/api/admin/requests/{$financeRequest->id}/contract/download",
            ]);
        }

        return $documents
            ->sortBy('sort_order')
            ->values();
    }

    public function options(FinanceRequest $financeRequest): array
    {
        $banks = Agent::query()
            ->with('bank:id,name,short_name,code')
            ->where('is_active', true)
            ->whereNotNull('bank_id')
            ->get()
            ->map(fn (Agent $agent) => $agent->bank)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($bank) => [
                'id' => (int) $bank->id,
                'name' => $bank->name,
                'short_name' => $bank->short_name,
                'code' => $bank->code,
            ])
            ->all();

        $agents = Agent::query()
            ->with('bank:id,name,short_name,code')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'company_name', 'agent_type', 'bank_id'])
            ->map(fn (Agent $agent) => [
                'id' => (int) $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'company_name' => $agent->company_name,
                'agent_type' => $agent->agent_type,
                'bank_id' => $agent->bank_id ? (int) $agent->bank_id : null,
                'bank_name' => $agent->bank?->name,
                'bank_short_name' => $agent->bank?->short_name,
                'bank_code' => $agent->bank?->code,
            ])
            ->values()
            ->all();

        return [
            'banks' => $banks,
            'agents' => $agents,
            'available_documents' => $this->buildAvailableDocuments($financeRequest)->all(),
        ];
    }

    public function assignAgents(
        FinanceRequest $financeRequest,
        array $assignments,
        ?int $actorUserId = null,
        ?string $reviewNote = null,
    ): FinanceRequest {
        if (! in_array($financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage, [
            FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
            FinanceRequestWorkflowStage::PROCESSING->value,
        ], true)) {
            throw ValidationException::withMessages([
                'workflow_stage' => 'Allowed bank agents can only be configured after the understudy has been approved.',
            ]);
        }

        $availableDocuments = $this->buildAvailableDocuments($financeRequest)->keyBy('key');

        if ($availableDocuments->isEmpty()) {
            throw ValidationException::withMessages([
                'documents' => 'There are no request-linked files available to assign to bank agents yet.',
            ]);
        }

        $normalizedAssignments = collect($assignments)
            ->map(function (array $assignment) use ($availableDocuments) {
                $documentKeys = collect($assignment['document_keys'] ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter(fn ($value) => $value !== '')
                    ->unique()
                    ->values();

                $missingDocumentKey = $documentKeys->first(fn (string $key) => ! $availableDocuments->has($key));
                if ($missingDocumentKey !== null) {
                    throw ValidationException::withMessages([
                        'assignments' => "The selected document [{$missingDocumentKey}] is not available on this request.",
                    ]);
                }

                return [
                    'agent_id' => (int) $assignment['agent_id'],
                    'document_keys' => $documentKeys->all(),
                ];
            })
            ->values();

        $agents = Agent::query()
            ->with('bank:id,name,short_name,code')
            ->whereIn('id', $normalizedAssignments->pluck('agent_id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($agents->count() !== $normalizedAssignments->count()) {
            throw ValidationException::withMessages([
                'assignments' => 'One or more selected agents are inactive or no longer available.',
            ]);
        }

        DB::transaction(function () use ($financeRequest, $normalizedAssignments, $agents, $availableDocuments, $actorUserId, $reviewNote) {
            $existingAssignments = $financeRequest->agentAssignments()
                ->where('is_active', true)
                ->get();

            foreach ($existingAssignments as $existingAssignment) {
                $existingAssignment->update([
                    'is_active' => false,
                    'unassigned_at' => now(),
                ]);
            }

            $assignedAgentsForTimeline = [];
            $documentCount = 0;

            foreach ($normalizedAssignments as $index => $assignmentPayload) {
                /** @var Agent $agent */
                $agent = $agents->get($assignmentPayload['agent_id']);

                $assignment = FinanceRequestAgentAssignment::create([
                    'finance_request_id' => $financeRequest->id,
                    'agent_id' => $agent->id,
                    'bank_id' => $agent->bank_id,
                    'assigned_by' => $actorUserId,
                    'is_active' => true,
                    'assigned_at' => now(),
                ]);

                foreach ($assignmentPayload['document_keys'] as $docIndex => $documentKey) {
                    $document = $availableDocuments->get($documentKey);

                    FinanceRequestAgentAssignmentDocument::create([
                        'finance_request_agent_assignment_id' => $assignment->id,
                        'finance_request_id' => $financeRequest->id,
                        'document_type' => $document['document_type'],
                        'document_id' => $document['document_id'],
                        'document_key' => $document['key'],
                        'group_label' => $document['group_label'],
                        'document_label' => $document['label'],
                        'file_name' => $document['file_name'],
                        'file_path' => $document['file_path'],
                        'disk' => $document['disk'],
                        'mime_type' => $document['mime_type'],
                        'file_extension' => $document['file_extension'],
                        'file_size' => $document['file_size'],
                        'sort_order' => ($index * 1000) + $docIndex,
                    ]);

                    $documentCount++;
                }

                $assignedAgentsForTimeline[] = [
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                    'bank_id' => $agent->bank_id,
                    'bank_name' => $agent->bank?->name,
                    'documents_count' => count($assignmentPayload['document_keys']),
                ];
            }

            $financeRequest->status = FinanceRequestStatus::ACTIVE;
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::PROCESSING;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.allowed_agents_configured',
                $actorUserId,
                'Allowed bank agents configured',
                'تم إعداد الوكلاء البنكيين المسموح لهم',
                $reviewNote ?: 'The admin selected the allowed bank agents and linked the request documents available for staff emails.',
                $reviewNote ?: 'اختار المسؤول الوكلاء البنكيين المسموح لهم وربط مستندات الطلب المتاحة لرسائل البريد الخاصة بالموظف.',
                [
                    'agents_count' => count($assignedAgentsForTimeline),
                    'documents_count' => $documentCount,
                    'assignments' => $assignedAgentsForTimeline,
                    'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING->value,
                ],
            );
        });

        return $financeRequest->fresh();
    }

    public function emailOptions(
        FinanceRequest $financeRequest,
        ?int $bankId = null,
        ?int $agentId = null,
    ): array {
        $assignments = $financeRequest->agentAssignments()
            ->with([
                'agent:id,name,email,phone,company_name,agent_type,bank_id',
                'agent.bank:id,name,short_name,code',
                'bank:id,name,short_name,code',
                'allowedDocuments',
            ])
            ->where('is_active', true)
            ->get();

        $banks = $assignments
            ->map(fn (FinanceRequestAgentAssignment $assignment) => $assignment->bank ?: $assignment->agent?->bank)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($bank) => [
                'id' => (int) $bank->id,
                'name' => $bank->name,
                'short_name' => $bank->short_name,
                'code' => $bank->code,
            ])
            ->all();

        $filteredAssignments = $assignments
            ->when($bankId, fn (Collection $collection) => $collection->filter(fn (FinanceRequestAgentAssignment $assignment) => (int) ($assignment->bank_id ?: $assignment->agent?->bank_id) === $bankId))
            ->when($agentId, fn (Collection $collection) => $collection->filter(fn (FinanceRequestAgentAssignment $assignment) => (int) $assignment->agent_id === $agentId))
            ->values();

        $agents = $filteredAssignments
            ->map(fn (FinanceRequestAgentAssignment $assignment) => $assignment->agent)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn (Agent $agent) => [
                'id' => (int) $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'company_name' => $agent->company_name,
                'agent_type' => $agent->agent_type,
                'bank_id' => $agent->bank_id ? (int) $agent->bank_id : null,
                'bank_name' => $agent->bank?->name,
                'bank_short_name' => $agent->bank?->short_name,
                'bank_code' => $agent->bank?->code,
            ])
            ->all();

        $allowedDocuments = $agentId
            ? $filteredAssignments
                ->flatMap(function (FinanceRequestAgentAssignment $assignment) {
                    return $assignment->allowedDocuments->map(function (FinanceRequestAgentAssignmentDocument $document) use ($assignment) {
                        return [
                            'key' => $document->document_key,
                            'document_type' => $document->document_type,
                            'document_id' => $document->document_id,
                            'group_label' => $document->group_label,
                            'label' => $document->document_label,
                            'file_name' => $document->file_name,
                            'download_url' => $this->downloadUrlForDocument($assignment->finance_request_id, $document->document_type, $document->document_id),
                            'agent_id' => (int) $assignment->agent_id,
                            'agent_name' => $assignment->agent?->name,
                            'bank_id' => $assignment->bank_id ? (int) $assignment->bank_id : ($assignment->agent?->bank_id ? (int) $assignment->agent->bank_id : null),
                        ];
                    });
                })
                ->groupBy('key')
                ->map(function (Collection $group) {
                    $first = $group->first();
                    return [
                        ...$first,
                        'agent_ids' => $group->pluck('agent_id')->filter()->unique()->values()->all(),
                        'agent_names' => $group->pluck('agent_name')->filter()->unique()->values()->all(),
                    ];
                })
                ->sortBy('label')
                ->values()
                ->all()
            : [];

        return [
            'banks' => $banks,
            'agents' => $agents,
            'allowed_documents' => $allowedDocuments,
            'has_assignments' => $assignments->isNotEmpty(),
            'can_email' => $assignments->isNotEmpty() && in_array($financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage, [
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
            ], true),
        ];
    }

    private function documentKey(string $type, int|string|null $id): string
    {
        return $type . ':' . $id;
    }

    private function headline(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'Request attachment';
        }

        return str($value)
            ->replace(['-', '_'], ' ')
            ->title()
            ->toString();
    }

    private function downloadUrlForDocument(int $financeRequestId, string $documentType, ?int $documentId): ?string
    {
        return match ($documentType) {
            'request_attachment' => $documentId ? "/api/admin/requests/{$financeRequestId}/attachments/{$documentId}/download" : null,
            'required_document' => $documentId ? "/api/admin/requests/{$financeRequestId}/required-documents/{$documentId}/download" : null,
            'additional_document' => $documentId ? "/api/admin/requests/{$financeRequestId}/additional-documents/{$documentId}/download" : null,
            'shareholder_id' => $documentId ? "/api/admin/requests/{$financeRequestId}/shareholders/{$documentId}/id-file/download" : null,
            'contract_pdf' => "/api/admin/requests/{$financeRequestId}/contract/download",
            default => null,
        };
    }
}
