<?php

namespace App\Models;

use App\Enums\FinanceRequestUpdateItemEditableBy;
use App\Enums\FinanceRequestUpdateItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceRequestUpdateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'update_batch_id',
        'finance_request_id',
        'requested_by',
        'updated_by',
        'reviewed_by',
        'item_type',
        'field_key',
        'question_id',
        'related_model_type',
        'related_model_id',
        'label_en',
        'label_ar',
        'instruction_en',
        'instruction_ar',
        'editable_by',
        'status',
        'is_required',
        'old_value_json',
        'new_value_json',
        'requested_at',
        'fulfilled_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'editable_by' => FinanceRequestUpdateItemEditableBy::class,
            'status' => FinanceRequestUpdateItemStatus::class,
            'is_required' => 'boolean',
            'old_value_json' => 'array',
            'new_value_json' => 'array',
            'requested_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function updateBatch(): BelongsTo
    {
        return $this->belongsTo(FinanceRequestUpdateBatch::class, 'update_batch_id');
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(RequestQuestion::class, 'question_id');
    }
}
