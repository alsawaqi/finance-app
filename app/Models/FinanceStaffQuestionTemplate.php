<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceStaffQuestionTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'question_text_en',
        'question_text_ar',
        'question_type',
        'options_json',
        'placeholder_en',
        'placeholder_ar',
        'help_text_en',
        'help_text_ar',
        'validation_rules',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function requestQuestions(): HasMany
    {
        return $this->hasMany(FinanceRequestStaffQuestion::class, 'finance_staff_question_template_id');
    }
}