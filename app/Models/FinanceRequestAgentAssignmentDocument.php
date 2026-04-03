<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceRequestAgentAssignmentDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_agent_assignment_id',
        'finance_request_id',
        'document_type',
        'document_id',
        'document_key',
        'group_label',
        'document_label',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_extension',
        'file_size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FinanceRequestAgentAssignment::class, 'finance_request_agent_assignment_id');
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }
}
