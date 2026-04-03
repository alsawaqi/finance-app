<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestTimeline extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'request_timeline';

    protected $fillable = [
        'finance_request_id',
        'actor_user_id',
        'event_type',
        'event_title',
        'event_title_en',
        'event_title_ar',
        'event_description',
        'event_description_en',
        'event_description_ar',
        'metadata_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'created_at'    => 'datetime',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}