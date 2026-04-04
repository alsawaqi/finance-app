<?php

namespace App\Models;

use App\Enums\RequestDocumentUploadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDocumentUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'document_upload_step_id',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_extension',
        'file_size',
        'status',
        'rejection_reason',
        'uploaded_by',
        'reviewed_by',
        'uploaded_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestDocumentUploadStatus::class,
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function documentUploadStep(): BelongsTo
    {
        return $this->belongsTo(DocumentUploadStep::class, 'document_upload_step_id');
    }

    // Backward-compatible alias used by eager loads like `documentUploads.step`.
    public function step(): BelongsTo
    {
        return $this->documentUploadStep();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}