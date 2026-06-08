<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceRequestStaffAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'staff_id',
        'assigned_by',
        'unassigned_by',
        'assignment_role',
        'notes',
        'is_primary',
        'is_active',
        'can_request_client_updates',
        'assigned_at',
        'unassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'    => 'boolean',
            'is_active'     => 'boolean',
            'can_request_client_updates' => 'boolean',
            'assigned_at'   => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function unassignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unassigned_by');
    }
}
