<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'question_id',
        'answer_value_json',
        'answer_text',
        'answered_by',
    ];

    protected function casts(): array
    {
        return [
            'answer_value_json' => 'array',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(RequestQuestion::class, 'question_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}