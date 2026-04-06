<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'question_text',
        'question_type',
        'finance_type',
        'options_json',
        'placeholder',
        'help_text',
        'validation_rules',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'is_required'  => 'boolean',
            'is_active'    => 'boolean',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RequestAnswer::class, 'question_id');
    }
}
