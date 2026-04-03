<?php

namespace App\Services;

use App\Enums\FinanceRequestUpdateBatchStatus;
use App\Enums\FinanceRequestUpdateBatchTargetRole;
use App\Enums\FinanceRequestUpdateItemEditableBy;
use App\Enums\FinanceRequestUpdateItemStatus;
 
use App\Models\FinanceRequest;
use App\Models\FinanceRequestUpdateBatch;
use App\Models\FinanceRequestUpdateItem;
use App\Models\RequestAnswer;
use App\Models\RequestAttachment;
use App\Models\RequestQuestion;
use App\Models\User;
use App\Support\RequestTimelineLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FinanceRequestUpdateService
{
    private const ATTACHMENT_CATEGORY_MAP = [
        'national_address_attachment' => 'national_address',
        'company_cr' => 'company_cr',
        'initial_submission' => 'initial_submission',
    ];

    public function __construct(
        private readonly FinanceRequestWorkflowService $workflowService,
    ) {
    }

    public function openClientUpdateBatch(FinanceRequest $financeRequest, User $actor, array $validated): FinanceRequestUpdateBatch
    {
        $existingOpenBatch = $financeRequest->updateBatches()
            ->whereIn('status', [
                FinanceRequestUpdateBatchStatus::OPEN->value,
                FinanceRequestUpdateBatchStatus::PARTIALLY_COMPLETED->value,
            ])
            ->latest('id')
            ->first();

        if ($existingOpenBatch) {
            throw ValidationException::withMessages([
                'update_batch' => 'There is already an open client update batch for this request.',
            ]);
        }

        $batch = FinanceRequestUpdateBatch::create([
            'finance_request_id' => $financeRequest->id,
            'requested_by' => $actor->id,
            'target_role' => FinanceRequestUpdateBatchTargetRole::CLIENT,
            'status' => FinanceRequestUpdateBatchStatus::OPEN,
            'reason_en' => $validated['reason_en'] ?? null,
            'reason_ar' => $validated['reason_ar'] ?? null,
            'opened_at' => now(),
            'return_status' => $financeRequest->status?->value ?? (string) $financeRequest->status,
            'return_workflow_stage' => $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage,
        ]);

        foreach ($validated['items'] as $row) {
            $oldValue = $this->resolveCurrentValue($financeRequest, $row);

            FinanceRequestUpdateItem::create([
                'update_batch_id' => $batch->id,
                'finance_request_id' => $financeRequest->id,
                'requested_by' => $actor->id,
                'item_type' => $row['item_type'],
                'field_key' => $row['field_key'] ?? null,
                'question_id' => $row['question_id'] ?? null,
                'label_en' => $row['label_en'] ?? $this->defaultLabelEn($row),
                'label_ar' => $row['label_ar'] ?? null,
                'instruction_en' => $row['instruction_en'] ?? null,
                'instruction_ar' => $row['instruction_ar'] ?? null,
                'editable_by' => $row['editable_by'] ?? FinanceRequestUpdateItemEditableBy::CLIENT->value,
                'status' => FinanceRequestUpdateItemStatus::PENDING,
                'is_required' => array_key_exists('is_required', $row) ? (bool) $row['is_required'] : true,
                'old_value_json' => $oldValue,
                'requested_at' => now(),
            ]);
        }

        $this->workflowService->moveToClientUpdateRequested($financeRequest, $batch, $actor->id);

        RequestTimelineLogger::log(
            $financeRequest,
            'request.update_batch_opened',
            $actor->id,
            'Client update request opened',
            'تم فتح طلب تحديث للعميل',
            $validated['reason_en'] ?? 'The admin requested targeted updates from the client.',
            $validated['reason_ar'] ?? 'طلب المسؤول تحديثات محددة من العميل.',
            [
                'update_batch_id' => $batch->id,
                'items_total' => count($validated['items']),
            ],
        );

        return $batch->fresh(['requester:id,name,email', 'items.question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required']);
    }

    public function submitClientValueUpdate(
        FinanceRequest $financeRequest,
        FinanceRequestUpdateItem $item,
        User $actor,
        mixed $value,
    ): FinanceRequestUpdateItem {
        $this->assertClientItemAccess($financeRequest, $item, $actor);

        if (! in_array($item->item_type, ['intake_field', 'request_answer'], true)) {
            throw ValidationException::withMessages([
                'item_type' => 'This update item expects a file upload, not a value payload.',
            ]);
        }

        $newValueJson = $item->item_type === 'intake_field'
        ? ['value' => $this->normalizeIntakeFieldValue((string) $item->field_key, $value, (bool) $item->is_required)]
        : $this->normalizeQuestionValue($item, $value);

        $item->forceFill([
            'new_value_json' => $newValueJson,
            'status' => FinanceRequestUpdateItemStatus::UPDATED,
            'updated_by' => $actor->id,
            'fulfilled_at' => now(),
        ])->save();

        $this->syncBatchProgress($item->updateBatch()->firstOrFail());

        RequestTimelineLogger::log(
            $financeRequest,
            'request.update_item_submitted',
            $actor->id,
            'Client submitted an update item',
            'قام العميل بإرسال عنصر تحديث',
            'The client submitted an updated value for: ' . ($item->label_en ?: $item->field_key ?: ('Question #' . $item->question_id)) . '.',
            'قام العميل بإرسال قيمة محدثة للعنصر المطلوب.',
            [
                'update_item_id' => $item->id,
                'update_batch_id' => $item->update_batch_id,
                'item_type' => $item->item_type,
                'field_key' => $item->field_key,
                'question_id' => $item->question_id,
            ],
        );

        return $item->fresh(['question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required']);
    }

    public function submitClientFileUpdate(
        FinanceRequest $financeRequest,
        FinanceRequestUpdateItem $item,
        User $actor,
        UploadedFile $file,
    ): FinanceRequestUpdateItem {
        $this->assertClientItemAccess($financeRequest, $item, $actor);

        if ($item->item_type !== 'attachment') {
            throw ValidationException::withMessages([
                'item_type' => 'This update item expects a value payload, not a file upload.',
            ]);
        }

        $path = $file->store('request-updates/client', 'public');

        $item->forceFill([
            'new_value_json' => [
                'category' => $this->attachmentCategoryFromFieldKey((string) $item->field_key),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getClientMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
            ],
            'status' => FinanceRequestUpdateItemStatus::UPDATED,
            'updated_by' => $actor->id,
            'fulfilled_at' => now(),
        ])->save();

        $this->syncBatchProgress($item->updateBatch()->firstOrFail());

        RequestTimelineLogger::log(
            $financeRequest,
            'request.update_item_file_submitted',
            $actor->id,
            'Client uploaded an update file',
            'قام العميل برفع ملف تحديث',
            'The client uploaded a replacement file for: ' . ($item->label_en ?: $item->field_key ?: 'requested file') . '.',
            'قام العميل برفع ملف بديل للعنصر المطلوب.',
            [
                'update_item_id' => $item->id,
                'update_batch_id' => $item->update_batch_id,
                'item_type' => $item->item_type,
                'field_key' => $item->field_key,
            ],
        );

        return $item->fresh(['question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required']);
    }

    public function reviewClientUpdateItem(
        FinanceRequest $financeRequest,
        FinanceRequestUpdateItem $item,
        User $actor,
        string $action,
        ?string $reviewNote = null,
    ): FinanceRequestUpdateItem {
        if ((int) $item->finance_request_id !== (int) $financeRequest->id) {
            throw ValidationException::withMessages([
                'update_item_id' => 'The selected update item does not belong to this request.',
            ]);
        }

        if ($action === 'approve') {
            if ($item->status !== FinanceRequestUpdateItemStatus::UPDATED) {
                throw ValidationException::withMessages([
                    'action' => 'Only submitted update items can be approved.',
                ]);
            }

            $this->applyApprovedItem($financeRequest, $item);

            $item->forceFill([
                'status' => FinanceRequestUpdateItemStatus::APPROVED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
        } else {
            if ($item->status === FinanceRequestUpdateItemStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'action' => 'Approved update items cannot be rejected back to the client because rollback is not implemented.',
                ]);
            }

            if ($item->status !== FinanceRequestUpdateItemStatus::UPDATED) {
                throw ValidationException::withMessages([
                    'action' => 'Only submitted update items can be rejected back to the client.',
                ]);
            }

            $item->forceFill([
                'status' => FinanceRequestUpdateItemStatus::REJECTED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
        }

        $batch = $item->updateBatch()->firstOrFail();
        $this->syncBatchProgress($batch);

        RequestTimelineLogger::log(
            $financeRequest,
            'request.update_item_reviewed',
            $actor->id,
            $action === 'approve' ? 'Client update item approved' : 'Client update item rejected',
            $action === 'approve' ? 'تم اعتماد عنصر التحديث من العميل' : 'تم رفض عنصر التحديث من العميل',
            $reviewNote ?: ($action === 'approve'
                ? 'The admin approved the submitted client update item.'
                : 'The admin rejected the submitted client update item and returned it to the client.'),
            $reviewNote ?: ($action === 'approve'
                ? 'اعتمد المسؤول عنصر التحديث المرسل من العميل.'
                : 'رفض المسؤول عنصر التحديث المرسل من العميل وأعاده إلى العميل.'),
            [
                'update_item_id' => $item->id,
                'update_batch_id' => $item->update_batch_id,
                'action' => $action,
                'review_note' => $reviewNote,
            ],
        );

        return $item->fresh(['question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required']);
    }

    public function getActiveClientBatch(FinanceRequest $financeRequest): ?FinanceRequestUpdateBatch
    {
        return $financeRequest->updateBatches()
            ->whereIn('status', [
                FinanceRequestUpdateBatchStatus::OPEN->value,
                FinanceRequestUpdateBatchStatus::PARTIALLY_COMPLETED->value,
            ])
            ->latest('id')
            ->with([
                'requester:id,name,email',
                'items' => fn ($query) => $query
                    ->whereIn('status', [
                        FinanceRequestUpdateItemStatus::PENDING->value,
                        FinanceRequestUpdateItemStatus::REJECTED->value,
                        FinanceRequestUpdateItemStatus::UPDATED->value,
                    ])
                    ->whereIn('editable_by', [
                        FinanceRequestUpdateItemEditableBy::CLIENT->value,
                        FinanceRequestUpdateItemEditableBy::BOTH->value,
                    ])
                    ->with('question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required')
                    ->orderBy('id'),
            ])
            ->first();
    }


    public function cancelClientUpdateBatch(
        FinanceRequest $financeRequest,
        FinanceRequestUpdateBatch $batch,
        User $actor,
    ): FinanceRequestUpdateBatch {
        if ((int) $batch->finance_request_id !== (int) $financeRequest->id) {
            throw ValidationException::withMessages([
                'update_batch_id' => 'The selected update batch does not belong to this request.',
            ]);
        }

        if (! in_array($batch->status, [
            FinanceRequestUpdateBatchStatus::OPEN,
            FinanceRequestUpdateBatchStatus::PARTIALLY_COMPLETED,
        ], true)) {
            throw ValidationException::withMessages([
                'update_batch_id' => 'Only active client update batches can be cancelled.',
            ]);
        }

        $batch->loadMissing('items', 'financeRequest');

        $cancelledCount = 0;
        $approvedCount = 0;

        foreach ($batch->items as $item) {
            if ($item->status === FinanceRequestUpdateItemStatus::APPROVED) {
                $approvedCount++;
                continue;
            }

            if ($item->status !== FinanceRequestUpdateItemStatus::CANCELLED) {
                $item->forceFill([
                    'status' => FinanceRequestUpdateItemStatus::CANCELLED,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ])->save();
                $cancelledCount++;
            }
        }

        $batch->forceFill([
            'status' => FinanceRequestUpdateBatchStatus::CANCELLED,
            'closed_at' => now(),
        ])->save();

        $this->workflowService->restoreAfterClientUpdateBatchCancellation($batch->financeRequest, $batch, $actor->id);

        RequestTimelineLogger::log(
            $financeRequest,
            'request.update_batch_cancelled',
            $actor->id,
            'Client update batch cancelled by admin',
            'ألغى المسؤول دفعة تحديث العميل',
            'The admin cancelled the active client update batch.',
            'ألغى المسؤول دفعة تحديث العميل النشطة.',
            [
                'update_batch_id' => $batch->id,
                'cancelled_items_count' => $cancelledCount,
                'approved_items_kept_count' => $approvedCount,
            ],
        );

        return $batch->fresh(['requester:id,name,email', 'items.question:id,code,question_text,question_type,options_json,placeholder,help_text,is_required']);
    }

    private function assertClientItemAccess(FinanceRequest $financeRequest, FinanceRequestUpdateItem $item, User $actor): void
    {
        if ((int) $financeRequest->user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'finance_request' => 'This request does not belong to the authenticated client.',
            ]);
        }

        if ((int) $item->finance_request_id !== (int) $financeRequest->id) {
            throw ValidationException::withMessages([
                'update_item_id' => 'The selected update item does not belong to this request.',
            ]);
        }

        if (! in_array($item->editable_by, [FinanceRequestUpdateItemEditableBy::CLIENT, FinanceRequestUpdateItemEditableBy::BOTH], true)) {
            throw ValidationException::withMessages([
                'update_item_id' => 'This update item is not editable by the client.',
            ]);
        }

        if (! in_array($item->status, [FinanceRequestUpdateItemStatus::PENDING, FinanceRequestUpdateItemStatus::REJECTED], true)) {
            throw ValidationException::withMessages([
                'update_item_id' => 'This update item is not currently open for client submission.',
            ]);
        }
    }

    private function syncBatchProgress(FinanceRequestUpdateBatch $batch): void
    {
        $batch->loadMissing('items', 'financeRequest');

        $items = $batch->items;
        $allApproved = $items->count() > 0 && $items->every(fn (FinanceRequestUpdateItem $item) => $item->status === FinanceRequestUpdateItemStatus::APPROVED);
        $hasAnyProgress = $items->contains(fn (FinanceRequestUpdateItem $item) => in_array($item->status, [
            FinanceRequestUpdateItemStatus::UPDATED,
            FinanceRequestUpdateItemStatus::APPROVED,
        ], true));

        if ($allApproved) {
            $batch->status = FinanceRequestUpdateBatchStatus::COMPLETED;
            $batch->closed_at = $batch->closed_at ?: now();
            $batch->save();

            $this->workflowService->restoreAfterClientUpdateBatch($batch->financeRequest, $batch);

            return;
        }

        $batch->status = $hasAnyProgress
            ? FinanceRequestUpdateBatchStatus::PARTIALLY_COMPLETED
            : FinanceRequestUpdateBatchStatus::OPEN;
        $batch->closed_at = null;
        $batch->save();
    }

    private function applyApprovedItem(FinanceRequest $financeRequest, FinanceRequestUpdateItem $item): void
    {
        $payload = $item->new_value_json ?? [];

        switch ($item->item_type) {
            case 'intake_field':
                $details = $financeRequest->intake_details_json ?? [];
                $details[$item->field_key] = Arr::get($payload, 'value');
                $financeRequest->intake_details_json = $details;

                if ($item->field_key === 'company_name') {
                    $financeRequest->company_name = Arr::get($payload, 'value');
                }

                if ($item->field_key === 'finance_request_type_id') {
                    $financeRequest->finance_request_type_id = Arr::get($payload, 'value') ? (int) Arr::get($payload, 'value') : null;
                }

                $financeRequest->latest_activity_at = now();
                $financeRequest->save();
                break;

            case 'request_answer':
                $answer = RequestAnswer::query()
                    ->where('finance_request_id', $financeRequest->id)
                    ->where('question_id', $item->question_id)
                    ->first();

                $values = [
                    'answer_text' => Arr::get($payload, 'answer_text'),
                    'answer_value_json' => Arr::get($payload, 'answer_value_json'),
                    'answered_by' => $item->updated_by,
                ];

                if ($answer) {
                    $answer->update($values);
                } else {
                    RequestAnswer::create(array_merge($values, [
                        'finance_request_id' => $financeRequest->id,
                        'question_id' => $item->question_id,
                    ]));
                }

                $financeRequest->latest_activity_at = now();
                $financeRequest->save();
                break;

            case 'attachment':
                $category = Arr::get($payload, 'category') ?: $this->attachmentCategoryFromFieldKey((string) $item->field_key);

                $attachment = RequestAttachment::query()
                    ->when($item->related_model_id, fn ($query) => $query->whereKey($item->related_model_id), fn ($query) => $query
                        ->where('finance_request_id', $financeRequest->id)
                        ->where('category', $category)
                        ->latest('id'))
                    ->first();

                $values = [
                    'category' => $category,
                    'file_name' => Arr::get($payload, 'file_name'),
                    'file_path' => Arr::get($payload, 'file_path'),
                    'disk' => Arr::get($payload, 'disk', 'public'),
                    'mime_type' => Arr::get($payload, 'mime_type'),
                    'file_extension' => Arr::get($payload, 'file_extension'),
                    'file_size' => Arr::get($payload, 'file_size'),
                    'uploaded_by' => $item->updated_by,
                ];

                if ($attachment) {
                    $attachment->update($values);
                } else {
                    RequestAttachment::create(array_merge($values, [
                        'finance_request_id' => $financeRequest->id,
                    ]));
                }

                $financeRequest->latest_activity_at = now();
                $financeRequest->save();
                break;

            default:
                throw ValidationException::withMessages([
                    'item_type' => 'Unsupported update item type: ' . $item->item_type,
                ]);
        }
    }

    private function resolveCurrentValue(FinanceRequest $financeRequest, array $row): ?array
    {
        return match ($row['item_type']) {
            'intake_field' => [
                'value' => data_get($financeRequest->intake_details_json ?? [], $row['field_key']),
            ],
            'request_answer' => $this->resolveCurrentAnswerValue($financeRequest, (int) $row['question_id']),
            'attachment' => $this->resolveCurrentAttachmentValue($financeRequest, (string) $row['field_key']),
            default => null,
        };
    }

    private function resolveCurrentAnswerValue(FinanceRequest $financeRequest, int $questionId): ?array
    {
        $answer = RequestAnswer::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('question_id', $questionId)
            ->first();

        if (! $answer) {
            return null;
        }

        return [
            'answer_text' => $answer->answer_text,
            'answer_value_json' => $answer->answer_value_json,
        ];
    }

    private function resolveCurrentAttachmentValue(FinanceRequest $financeRequest, string $fieldKey): ?array
    {
        $category = $this->attachmentCategoryFromFieldKey($fieldKey);

        $attachment = RequestAttachment::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('category', $category)
            ->latest('id')
            ->first();

        if (! $attachment) {
            return null;
        }

        return [
            'category' => $category,
            'file_name' => $attachment->file_name,
            'file_path' => $attachment->file_path,
            'disk' => $attachment->disk,
            'mime_type' => $attachment->mime_type,
            'file_extension' => $attachment->file_extension,
            'file_size' => $attachment->file_size,
            'attachment_id' => $attachment->id,
        ];
    }

    private function normalizeQuestionValue(FinanceRequestUpdateItem $item, mixed $value): array
    {
        $question = $item->question ?: RequestQuestion::findOrFail($item->question_id);
        $type = (string) $question->question_type;

        if ($type === 'checkbox') {
            $values = collect(is_array($value) ? $value : [$value])
                ->map(fn ($entry) => trim((string) $entry))
                ->filter(fn ($entry) => $entry !== '')
                ->values()
                ->all();

            if ($values === []) {
                throw ValidationException::withMessages([
                    'value' => 'Please select at least one value for this checklist question.',
                ]);
            }

            return [
                'answer_text' => implode(', ', $values),
                'answer_value_json' => $values,
            ];
        }

        $text = is_array($value) ? '' : trim((string) $value);

        if ($text === '') {
            throw ValidationException::withMessages([
                'value' => 'Please provide a value for this update item.',
            ]);
        }

        if (in_array($type, ['number', 'currency'], true) && ! is_numeric($text)) {
            throw ValidationException::withMessages([
                'value' => 'Please provide a valid numeric value.',
            ]);
        }

        return [
            'answer_text' => $text,
            'answer_value_json' => in_array($type, ['select', 'radio'], true) ? $text : null,
        ];
    }

    private function normalizeIntakeFieldValue(string $fieldKey, mixed $value, bool $isRequired = true): mixed
    {
        if (is_array($value)) {
            throw ValidationException::withMessages([
                'value' => 'This update item expects a single value, not a list.',
            ]);
        }

        $normalized = is_string($value) ? trim($value) : $value;
        $isBlank = $normalized === null || (is_string($normalized) && $normalized === '');

        if ($isBlank) {
            if ($isRequired) {
                throw ValidationException::withMessages([
                    'value' => 'Please provide a value for this update item.',
                ]);
            }

            return null;
        }

        if ($fieldKey === 'requested_amount') {
            if (! is_numeric($normalized)) {
                throw ValidationException::withMessages([
                    'value' => 'Requested amount must be numeric.',
                ]);
            }

            return (float) $normalized;
        }

        if ($fieldKey === 'finance_request_type_id') {
            if (! is_numeric($normalized)) {
                throw ValidationException::withMessages([
                    'value' => 'Please select a valid finance request type.',
                ]);
            }

            return (int) $normalized;
        }

        return $normalized;
    }

    private function attachmentCategoryFromFieldKey(string $fieldKey): string
    {
        if (! array_key_exists($fieldKey, self::ATTACHMENT_CATEGORY_MAP)) {
            throw ValidationException::withMessages([
                'field_key' => 'Unsupported attachment field key: ' . $fieldKey,
            ]);
        }

        return self::ATTACHMENT_CATEGORY_MAP[$fieldKey];
    }

    private function defaultLabelEn(array $row): string
    {
        return match ($row['item_type']) {
            'intake_field' => ucfirst(str_replace('_', ' ', (string) ($row['field_key'] ?? 'field'))),
            'request_answer' => 'Question update',
            'attachment' => ucfirst(str_replace('_', ' ', (string) ($row['field_key'] ?? 'attachment'))),
            default => 'Requested update',
        };
    }
}
