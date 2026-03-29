<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\FinanceRequest;

class ContractDocumentBuilder
{
    public static function buildHtml(FinanceRequest $financeRequest, array $terms, ?Contract $contract = null): string
    {
        $client = $financeRequest->client;
        $details = (array) ($financeRequest->intake_details_json ?? []);
        $commission = trim((string) ($terms['commission'] ?? ''));
        $interest = trim((string) ($terms['interest'] ?? ''));
        $paymentPeriod = trim((string) ($terms['payment_period'] ?? ''));
        $generalTerms = array_values(array_filter(array_map(static fn ($item) => trim((string) $item), (array) ($terms['general_terms'] ?? []))));
        $specialTerms = trim((string) ($terms['special_terms'] ?? ''));

        $termLines = [];
        if ($commission !== '') {
            $termLines[] = 'Commission: ' . e($commission);
        }
        if ($interest !== '') {
            $termLines[] = 'Interest: ' . e($interest);
        }
        if ($paymentPeriod !== '') {
            $termLines[] = 'Payment Period: ' . e($paymentPeriod);
        }
        foreach ($generalTerms as $item) {
            $termLines[] = e($item);
        }
        if ($specialTerms !== '') {
            $termLines[] = e($specialTerms);
        }

        $termsList = '';
        foreach ($termLines as $line) {
            $termsList .= '<li>' . $line . '</li>';
        }

        $requestReference = e($financeRequest->reference_number);
        $approvalReference = e((string) ($financeRequest->approval_reference_number ?? 'Pending assignment'));
        $clientName = e((string) ($details['full_name'] ?? $details['name'] ?? $client?->name ?? 'Client'));
        $countryValue = (string) ($details['country_name'] ?? $details['country_code'] ?? $details['country'] ?? '');
        $country = e($countryValue);
        $requestedAmount = e((string) ($details['requested_amount'] ?? ''));
        $financeType = e((string) ($details['finance_type'] ?? ''));
        $notes = e((string) ($details['notes'] ?? ''));
        $versionNo = e((string) ($contract?->version_no ?? '1'));
        $generatedAt = e(optional($contract?->generated_at)->format('Y-m-d H:i'));
        $adminSignedAt = e(optional($contract?->admin_signed_at)->format('Y-m-d H:i'));
        $clientSignedAt = e(optional($contract?->client_signed_at)->format('Y-m-d H:i'));

        $adminSignBlock = self::buildSignatureBlock(
            'Admin Signature',
            $contract?->admin_signature_path,
            $adminSignedAt !== '' ? 'Signed at: ' . $adminSignedAt : 'Pending admin signature'
        );

        $clientSignBlock = self::buildSignatureBlock(
            'Client Signature',
            $contract?->client_signature_path,
            $clientSignedAt !== '' ? 'Signed at: ' . $clientSignedAt : 'Pending client signature'
        );

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body{font-family: DejaVu Sans, sans-serif; color:#0f172a; font-size:12px; line-height:1.55;}
        .page{padding:32px;}
        .header{border-bottom:2px solid #0f172a; padding-bottom:12px; margin-bottom:22px;}
        .header h1{margin:0 0 6px; font-size:22px;}
        .muted{color:#475569;}
        .section{margin-bottom:18px;}
        .section h2{font-size:15px; margin:0 0 8px; padding-bottom:6px; border-bottom:1px solid #cbd5e1;}
        .grid{width:100%; border-collapse:collapse;}
        .grid td{padding:8px 10px; border:1px solid #cbd5e1; vertical-align:top;}
        .grid td.label{width:34%; background:#f8fafc; font-weight:bold;}
        ol{padding-left:18px; margin:8px 0 0;}
        .note{background:#f8fafc; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; white-space:pre-line;}
        .signatures{width:100%; margin-top:28px; border-collapse:separate; border-spacing:18px 0;}
        .signature-box{width:50%; border-top:1px solid #334155; padding-top:8px; min-height:110px;}
        .signature-label{font-size:11px; color:#475569; margin-bottom:8px; font-weight:bold;}
        .signature-meta{font-size:10px; color:#64748b; margin-top:6px;}
        .signature-image{max-width:180px; max-height:70px;}
        .signature-empty{height:70px; color:#94a3b8; font-size:11px;}
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <h1>Finance Contract</h1>
        <div class="muted">Request Ref: {$requestReference}</div>
        <div class="muted">Approval Ref: {$approvalReference}</div>
        <div class="muted">Contract Version: {$versionNo}</div>
        <div class="muted">Generated At: {$generatedAt}</div>
    </div>

    <div class="section">
        <h2>Applicant Summary</h2>
        <table class="grid">
            <tr><td class="label">Applicant Name</td><td>{$clientName}</td></tr>
            <tr><td class="label">Country</td><td>{$country}</td></tr>
            <tr><td class="label">Requested Amount</td><td>{$requestedAmount}</td></tr>
            <tr><td class="label">Finance Type</td><td>{$financeType}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>Terms and Conditions</h2>
        <ol>{$termsList}</ol>
    </div>

    <div class="section">
        <h2>Additional Notes</h2>
        <div class="note">{$notes}</div>
    </div>

    <table class="signatures">
        <tr>
            <td class="signature-box">{$adminSignBlock}</td>
            <td class="signature-box">{$clientSignBlock}</td>
        </tr>
    </table>
</div>
</body>
</html>
HTML;
    }

    private static function buildSignatureBlock(string $label, ?string $relativePath, string $meta): string
    {
        $imageBlock = '<div class="signature-empty">Signature pending</div>';

        if ($relativePath) {
            $absolutePath = storage_path('app/public/' . ltrim($relativePath, '/'));
            if (is_file($absolutePath)) {
                $imageBlock = '<img class="signature-image" src="' . e($absolutePath) . '" alt="' . e($label) . '" />';
            }
        }

        return '<div class="signature-label">' . e($label) . '</div>'
            . $imageBlock
            . '<div class="signature-meta">' . e($meta) . '</div>';
    }
}
