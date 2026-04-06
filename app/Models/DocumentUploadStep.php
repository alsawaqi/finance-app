<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentUploadStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_required',
        'is_multiple',
        'allowed_file_types_json',
        'max_file_size_mb',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required'             => 'boolean',
            'is_multiple'             => 'boolean',
            'allowed_file_types_json' => 'array',
            'is_active'               => 'boolean',
        ];
    }

    public function requestDocumentUploads(): HasMany
    {
        return $this->hasMany(RequestDocumentUpload::class, 'document_upload_step_id');
    }
}
