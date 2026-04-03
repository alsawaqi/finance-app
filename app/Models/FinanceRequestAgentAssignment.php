<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceRequestAgentAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'agent_id',
        'bank_id',
        'assigned_by',
        'is_active',
        'assigned_at',
        'unassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function allowedDocuments(): HasMany
    {
        return $this->hasMany(FinanceRequestAgentAssignmentDocument::class, 'finance_request_agent_assignment_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}

