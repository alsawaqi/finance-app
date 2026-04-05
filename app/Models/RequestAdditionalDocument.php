<?php

namespace App\Models;

use App\Enums\RequestAdditionalDocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestAdditionalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'requested_by',
        'title',
        'reason',
        'status',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_extension',
        'file_size',
        'uploaded_by',
        'reviewed_by',
        'requested_at',
        'uploaded_at',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestAdditionalDocumentStatus::class,
            'file_size' => 'integer',
            'requested_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Alias for {@see requester()} — same as `requester`; use either name in eager loads. */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Alias for {@see uploader()} — same as `uploader`; use either name in eager loads. */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
