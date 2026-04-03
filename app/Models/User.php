<?php

namespace App\Models;

use App\Enums\UserAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'smtp_username',
        'smtp_password',
        'smtp_sender_name',
        'avatar_path',
        'account_type',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'smtp_password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'smtp_enabled' => 'boolean',
            'smtp_verified_at' => 'datetime',
            'smtp_password' => 'encrypted',
            'inbox_last_synced_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => UserAccountType::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->account_type === UserAccountType::ADMIN || $this->hasRole('admin');
    }

    public function isStaff(): bool
    {
        return $this->account_type === UserAccountType::STAFF || $this->hasRole('staff');
    }

    public function isClient(): bool
    {
        return $this->account_type === UserAccountType::CLIENT || $this->hasRole('client');
    }

    public function smtpSenderEmail(): ?string
    {
        $candidate = trim((string) ($this->smtp_username ?: $this->email ?: ''));

        return $candidate !== '' ? $candidate : null;
    }

    public function smtpSenderName(): string
    {
        $candidate = trim((string) ($this->smtp_sender_name ?: $this->name ?: ''));

        return $candidate !== '' ? $candidate : (string) ($this->name ?: 'Finance Staff');
    }

    public function hasStoredSmtpPassword(): bool
    {
        return filled($this->smtp_password);
    }

    public function hasVerifiedMailboxSettings(): bool
    {
        return $this->smtp_enabled
            && $this->smtp_verified_at !== null
            && filled($this->smtpSenderEmail())
            && $this->hasStoredSmtpPassword();
    }

    public function mailboxSettingsSummary(): array
    {
        return [
            'sender_email' => $this->smtpSenderEmail(),
            'sender_name' => $this->smtpSenderName(),
            'smtp_username' => $this->smtp_username,
            'smtp_enabled' => (bool) $this->smtp_enabled,
            'smtp_verified_at' => optional($this->smtp_verified_at)?->toISOString(),
            'has_smtp_password' => $this->hasStoredSmtpPassword(),
            'smtp_last_error' => $this->smtp_last_error,
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


    public function submittedUnderstudyFinanceRequests(): HasMany
    {
        return $this->hasMany(FinanceRequest::class, 'understudy_submitted_by');
    }

    public function reviewedUnderstudyFinanceRequests(): HasMany
    {
        return $this->hasMany(FinanceRequest::class, 'understudy_reviewed_by');
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

    public function mailboxMessages(): HasMany
    {
        return $this->hasMany(MailboxMessage::class, 'user_id');
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