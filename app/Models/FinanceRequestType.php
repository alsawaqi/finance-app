<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceRequestType extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function financeRequests(): HasMany
    {
        return $this->hasMany(FinanceRequest::class, 'finance_request_type_id');
    }
}
