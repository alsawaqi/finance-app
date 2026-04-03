<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxMessageAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox_message_id',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_extension',
        'file_size',
        'content_id',
        'sort_order',
    ];

    public function mailboxMessage(): BelongsTo
    {
        return $this->belongsTo(MailboxMessage::class, 'mailbox_message_id');
    }
}