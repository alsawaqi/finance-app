<?php

namespace App\Models;

use App\Enums\FinanceRequestStaffQuestionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceRequestStaffQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'finance_staff_question_template_id',
        'asked_by',
        'assigned_to',
        'question_code',
        'question_text_en',
        'question_text_ar',
        'question_type',
        'options_json',
        'placeholder_en',
        'placeholder_ar',
        'help_text_en',
        'help_text_ar',
        'validation_rules',
        'answer_text',
        'answer_json',
        'status',
        'is_required',
        'sort_order',
        'metadata_json',
        'asked_at',
        'answered_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FinanceRequestStaffQuestionStatus::class,
            'options_json' => 'array',
            'answer_json' => 'array',
            'is_required' => 'boolean',
            'metadata_json' => 'array',
            'asked_at' => 'datetime',
            'answered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FinanceStaffQuestionTemplate::class, 'finance_staff_question_template_id');
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}