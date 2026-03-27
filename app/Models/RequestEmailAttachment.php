<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestEmailAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_email_id',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_extension',
        'file_size',
    ];

    public function requestEmail(): BelongsTo
    {
        return $this->belongsTo(RequestEmail::class, 'request_email_id');
    }
}