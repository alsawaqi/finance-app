<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_request_id',
        'contract_template_id',
        'version_no',
        'contract_content',
        'terms_json',
        'contract_pdf_path',
        'generated_by',
        'generated_at',
        'admin_signed_at',
        'admin_signed_by',
        'admin_signature_path',
        'client_signed_at',
        'client_signed_by',
        'client_signature_path',
        'status',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'terms_json' => 'array',
            'status' => ContractStatus::class,
            'generated_at' => 'datetime',
            'admin_signed_at' => 'datetime',
            'client_signed_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    public function financeRequest(): BelongsTo
    {
        return $this->belongsTo(FinanceRequest::class, 'finance_request_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function adminSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_signed_by');
    }

    public function clientSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_signed_by');
    }
}