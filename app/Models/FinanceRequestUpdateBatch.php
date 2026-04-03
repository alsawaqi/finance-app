<?php

namespace App\Models;

use App\Enums\FinanceRequestUpdateBatchStatus;
use App\Enums\FinanceRequestUpdateBatchTargetRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceRequestUpdateBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'requested_by',
        'target_role',
        'status',
        'return_status',
        'return_workflow_stage',
        'reason_en',
        'reason_ar',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_role' => FinanceRequestUpdateBatchTargetRole::class,
            'status' => FinanceRequestUpdateBatchStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinanceRequestUpdateItem::class, 'update_batch_id');
    }
}
