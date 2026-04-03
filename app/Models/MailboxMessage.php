<?php

namespace App\Models;

use App\Models\MailboxMessageAttachment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MailboxMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'folder_name',
        'provider_uid',
        'message_id',
        'in_reply_to',
        'references_header',
        'subject',
        'from_email',
        'from_name',
        'to_emails_json',
        'cc_emails_json',
        'body_text',
        'body_html',
        'received_at',
        'is_read',
        'has_attachments',
    ];

    protected function casts(): array
    {
        return [
            'to_emails_json' => 'array',
            'cc_emails_json' => 'array',
            'received_at' => 'datetime',
            'is_read' => 'boolean',
            'has_attachments' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailboxMessageAttachment::class, 'mailbox_message_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function preview(int $limit = 160): string
    {
        $source = $this->body_text ?: strip_tags((string) $this->body_html);
        return Str::limit(trim((string) $source), $limit);
    }
}