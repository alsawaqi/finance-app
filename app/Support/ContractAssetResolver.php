<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\FinanceRequest;

class ContractAssetResolver
{
    public static function resolvePrimaryAsset(FinanceRequest $financeRequest, Contract $contract): ?array
    {
        if (filled($contract->admin_commercial_contract_path)) {
            return [
                'source' => 'admin_commercial_contract',
                'path' => $contract->admin_commercial_contract_path,
                'name' => $contract->admin_commercial_contract_name ?: ('admin-commercial-' . $financeRequest->reference_number),
                'mime_type' => $contract->admin_commercial_contract_mime_type,
            ];
        }

        if (filled($contract->client_commercial_contract_path)) {
            return [
                'source' => 'client_commercial_contract',
                'path' => $contract->client_commercial_contract_path,
                'name' => $contract->client_commercial_contract_name ?: ('client-commercial-' . $financeRequest->reference_number),
                'mime_type' => $contract->client_commercial_contract_mime_type,
            ];
        }

        if (filled($contract->admin_uploaded_contract_path)) {
            return [
                'source' => 'admin_uploaded_contract',
                'path' => $contract->admin_uploaded_contract_path,
                'name' => $contract->admin_uploaded_contract_name ?: ('contract-' . $financeRequest->reference_number),
                'mime_type' => $contract->admin_uploaded_contract_mime_type,
            ];
        }

        if (! filled($contract->contract_pdf_path)) {
            return null;
        }

        return [
            'source' => 'contract_pdf',
            'path' => $contract->contract_pdf_path,
            'name' => 'contract-' . $financeRequest->reference_number . '.pdf',
            'mime_type' => 'application/pdf',
        ];
    }
}
