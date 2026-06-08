<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestEmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'subject',
        'body',
        'fields_json',
        'created_by',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fields_json' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
