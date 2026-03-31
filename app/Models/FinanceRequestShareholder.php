<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceRequestShareholder extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'shareholder_name',
        'phone_country_code',
        'phone_number',
        'id_number',
        'id_file_name',
        'id_file_path',
        'disk',
        'mime_type',
        'file_extension',
        'file_size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }
}