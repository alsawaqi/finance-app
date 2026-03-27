<?php

namespace App\Models;

use App\Enums\RequestEmailDeliveryStatus;
use App\Enums\RequestEmailDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'direction',
        'sent_by',
        'subject',
        'body',
        'provider_message_id',
        'thread_key',
        'delivery_status',
        'from_email',
        'to_emails_json',
        'cc_emails_json',
        'bcc_emails_json',
        'sent_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => RequestEmailDirection::class,
            'delivery_status' => RequestEmailDeliveryStatus::class,
            'to_emails_json' => 'array',
            'cc_emails_json' => 'array',
            'bcc_emails_json' => 'array',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(
            Agent::class,
            'request_email_agents',
            'request_email_id',
            'agent_id'
        )->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestEmailAttachment::class, 'request_email_id');
    }
}