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
        'contract_source',
        'client_signature_skipped',
        'requires_commercial_registration',
        'admin_uploaded_contract_name',
        'admin_uploaded_contract_path',
        'admin_uploaded_contract_mime_type',
        'admin_uploaded_contract_size',
        'admin_uploaded_contract_at',
        'client_commercial_contract_name',
        'client_commercial_contract_path',
        'client_commercial_contract_mime_type',
        'client_commercial_contract_size',
        'client_commercial_uploaded_at',
        'admin_commercial_contract_name',
        'admin_commercial_contract_path',
        'admin_commercial_contract_mime_type',
        'admin_commercial_contract_size',
        'admin_commercial_uploaded_at',
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
            'admin_uploaded_contract_size' => 'integer',
            'admin_uploaded_contract_at' => 'datetime',
            'client_commercial_contract_size' => 'integer',
            'client_commercial_uploaded_at' => 'datetime',
            'admin_commercial_contract_size' => 'integer',
            'admin_commercial_uploaded_at' => 'datetime',
            'client_signature_skipped' => 'boolean',
            'requires_commercial_registration' => 'boolean',
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
