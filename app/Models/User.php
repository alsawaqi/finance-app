<?php

namespace App\Models;

use App\Enums\UserAccountType;
use App\Notifications\Auth\AuthResetPasswordNotification;
use App\Notifications\Auth\AuthVerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
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
            'last_login_at' => 'datetime',
            'smtp_verified_at' => 'datetime',
            'smtp_enabled' => 'boolean',
            'is_active' => 'boolean',
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

    public function mailboxSettingsSummary(): array
    {
        return [
            'smtp_username' => $this->smtp_username,
            'smtp_sender_name' => $this->smtp_sender_name,
            'smtp_enabled' => (bool) $this->smtp_enabled,
            'smtp_verified_at' => $this->smtp_verified_at?->toISOString(),
            'smtp_last_error' => $this->smtp_last_error,
            'has_smtp_password' => $this->hasStoredSmtpPassword(),
        ];
    }

    public function smtpSenderEmail(): string
    {
        return trim((string) ($this->smtp_username ?: $this->email));
    }

    public function smtpSenderName(): string
    {
        return trim((string) ($this->smtp_sender_name ?: $this->name));
    }

    public function hasStoredSmtpPassword(): bool
    {
        return filled($this->smtp_password);
    }

    /**
     * Staff mailbox is enabled, verified from Mail Settings, and has a stored SMTP password.
     * Matches the staff UI "mailbox ready" checks for sending / syncing.
     */
    public function hasVerifiedMailboxSettings(): bool
    {
        return (bool) $this->smtp_enabled
            && $this->smtp_verified_at !== null
            && $this->hasStoredSmtpPassword();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AuthResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new AuthVerifyEmailNotification());
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