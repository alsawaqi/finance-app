<?php

namespace App\Models;

use App\Enums\RequestCommentVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'user_id',
        'parent_id',
        'comment_text',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => RequestCommentVisibility::class,
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RequestComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(RequestComment::class, 'parent_id');
    }
}