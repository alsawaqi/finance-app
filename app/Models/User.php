<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
 
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'account_type',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
        ];
    }

    public function financeRequests(): HasMany
    {
        return $this->hasMany(FinanceRequest::class, 'user_id');
    }

    public function primaryAssignedFinanceRequests(): HasMany
    {
        return $this->hasMany(FinanceRequest::class, 'primary_staff_id');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(FinanceRequestStaffAssignment::class, 'staff_id');
    }

    public function assignedByMe(): HasMany
    {
        return $this->hasMany(FinanceRequestStaffAssignment::class, 'assigned_by');
    }

    public function requestAnswers(): HasMany
    {
        return $this->hasMany(RequestAnswer::class, 'answered_by');
    }

    public function uploadedRequestAttachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'uploaded_by');
    }

    public function uploadedRequestDocuments(): HasMany
    {
        return $this->hasMany(RequestDocumentUpload::class, 'uploaded_by');
    }

    public function reviewedRequestDocuments(): HasMany
    {
        return $this->hasMany(RequestDocumentUpload::class, 'reviewed_by');
    }

    public function generatedContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'generated_by');
    }

    public function adminSignedContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'admin_signed_by');
    }

    public function clientSignedContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'client_signed_by');
    }

    public function requestComments(): HasMany
    {
        return $this->hasMany(RequestComment::class, 'user_id');
    }

    public function sentRequestEmails(): HasMany
    {
        return $this->hasMany(RequestEmail::class, 'sent_by');
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(RequestTimeline::class, 'actor_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function createdAgents(): HasMany
    {
        return $this->hasMany(Agent::class, 'created_by');
    }

    public function createdContractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class, 'created_by');
    }

    public function updatedContractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class, 'updated_by');
    }
}