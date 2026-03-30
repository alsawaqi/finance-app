<?php

namespace App\Models;

use App\Enums\FinanceRequestPriority;
use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'approval_reference_number',
        'user_id',
        'primary_staff_id',
        'current_contract_id',
        'applicant_type',
        'company_name',
        'status',
        'workflow_stage',
        'priority',
        'intake_details_json',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'cancelled_at',
        'latest_assignment_at',
        'latest_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FinanceRequestStatus::class,
            'workflow_stage' => FinanceRequestWorkflowStage::class,
            'priority' => FinanceRequestPriority::class,
            'intake_details_json' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'latest_assignment_at' => 'datetime',
            'latest_activity_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function primaryStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_staff_id');
    }

    public function currentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'current_contract_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FinanceRequestStaffAssignment::class, 'finance_request_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RequestAnswer::class, 'finance_request_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'finance_request_id');
    }

    public function documentUploads(): HasMany
    {
        return $this->hasMany(RequestDocumentUpload::class, 'finance_request_id');
    }

    public function shareholders(): HasMany
    {
        return $this->hasMany(FinanceRequestShareholder::class, 'finance_request_id')->orderBy('sort_order');
    }

    public function additionalDocuments(): HasMany
    {
        return $this->hasMany(RequestAdditionalDocument::class, 'finance_request_id')->latest('id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'finance_request_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RequestComment::class, 'finance_request_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(RequestEmail::class, 'finance_request_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(RequestTimeline::class, 'finance_request_id')->orderBy('created_at');
    }
}
