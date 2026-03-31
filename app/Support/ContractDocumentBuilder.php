<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\FinanceRequest;

class ContractDocumentBuilder
{
    public static function buildPdfHtml(FinanceRequest $financeRequest, string $bodyHtml, ?Contract $contract = null): string
    {
        $requestReference = e((string) ($financeRequest->reference_number ?? '—'));
        $approvalReference = e((string) ($financeRequest->approval_reference_number ?? '—'));
        $versionNo = e((string) ($contract?->version_no ?? '1'));
        $generatedAt = e(self::formatDateTime($contract?->generated_at));
        $adminSignedAt = e(self::formatDateTime($contract?->admin_signed_at));
        $clientSignedAt = e(self::formatDateTime($contract?->client_signed_at));

        $adminSignBlock = self::buildSignatureBlock(
            'توقيع الطرف الأول',
            $contract?->admin_signature_path,
            $adminSignedAt !== '' ? 'تم التوقيع بتاريخ: ' . $adminSignedAt : 'بانتظار توقيع الطرف الأول'
        );

        $clientSignBlock = self::buildSignatureBlock(
            'توقيع الطرف الثاني',
            $contract?->client_signature_path,
            $clientSignedAt !== '' ? 'تم التوقيع بتاريخ: ' . $clientSignedAt : 'بانتظار توقيع الطرف الثاني'
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 18mm 14mm 20mm 14mm; }

        html, body {
            font-family: contractarabic, dejavusans, sans-serif;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.95;
            direction: rtl;
            text-align: right;
        }

        body, div, p, span, li, td, th, h1, h2, h3, h4 {
            direction: rtl;
            text-align: right;
            unicode-bidi: embed;
        }

        .ltr {
            direction: ltr;
            unicode-bidi: bidi-override;
            text-align: left;
            display: inline-block;
        }

        .contract-meta {
            margin-bottom: 18px;
            padding: 10px 14px;
            border: 1px solid #dbe4f0;
            border-radius: 10px;
            background: #f8fbff;
        }

        .contract-meta div {
            margin: 2px 0;
            color: #334155;
            font-size: 11px;
        }

        .contract-doc {
            direction: rtl;
            text-align: right;
        }

        .contract-doc * {
            box-sizing: border-box;
        }

        .contract-doc h1,
        .contract-doc h2,
        .contract-doc h3,
        .contract-doc h4 {
            margin: 0 0 10px;
            color: #0f172a;
        }

        .contract-doc p {
            margin: 0 0 12px;
        }

        .contract-doc ul,
        .contract-doc ol {
            margin: 0 0 14px;
            padding-right: 22px;
            padding-left: 0;
        }

        .contract-doc li {
            margin-bottom: 8px;
        }

        .contract-doc .contract-doc__issue {
            font-size: 12px;
            color: #475569;
            margin-bottom: 18px;
        }

        .contract-doc .contract-doc__title {
            font-size: 22px;
            text-align: center;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .contract-doc .contract-doc__date {
            margin-bottom: 18px;
            text-align: center;
        }

        .contract-doc .contract-doc__party {
            padding: 14px 16px;
            border: 1px solid #dbe4f0;
            border-radius: 10px;
            background: #fff;
            margin-bottom: 14px;
        }

        .contract-doc .contract-doc__party-label {
            display: block;
            font-weight: 700;
            color: #475569;
            margin-bottom: 8px;
        }

        .contract-doc .contract-doc__section-title {
            font-size: 16px;
            font-weight: 700;
            margin: 24px 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #cbd5e1;
        }

        .contract-doc .contract-doc__intro {
            font-weight: 700;
            text-align: center;
            margin: 24px 0 16px;
        }

        .contract-doc .contract-doc__bank-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 18px;
        }

        .contract-doc .contract-doc__bank-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
        }

        .contract-doc .contract-doc__bank-table td:first-child {
            background: #f8fafc;
            font-weight: 700;
            width: 28%;
        }

        .contract-doc .contract-doc__emphasis {
            font-weight: 700;
        }

        .signature-table {
            width: 100%;
            margin-top: 28px;
            border-collapse: separate;
            border-spacing: 18px 0;
        }

        .signature-box {
            width: 50%;
            border-top: 1px solid #334155;
            padding-top: 8px;
            min-height: 120px;
            vertical-align: top;
        }

        .signature-label {
            font-size: 12px;
            color: #475569;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .signature-meta {
            font-size: 10px;
            color: #64748b;
            margin-top: 6px;
        }

        .signature-image {
            max-width: 180px;
            max-height: 70px;
        }

        .signature-empty {
            height: 70px;
            color: #94a3b8;
            font-size: 11px;
        }
    </style>
</head>
<body lang="ar" dir="rtl">
    <div class="contract-meta" lang="ar" dir="rtl">
        <div>مرجع الطلب: <span class="ltr">{$requestReference}</span></div>
        <div>مرجع الموافقة: <span class="ltr">{$approvalReference}</span></div>
        <div>نسخة العقد: <span class="ltr">{$versionNo}</span></div>
        <div>تاريخ إنشاء النسخة: <span class="ltr">{$generatedAt}</span></div>
    </div>

    <div class="contract-doc" lang="ar" dir="rtl">{$bodyHtml}</div>

    <table class="signature-table">
        <tr>
            <td class="signature-box">{$clientSignBlock}</td>
            <td class="signature-box">{$adminSignBlock}</td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private static function formatDateTime(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return $value->timezone('Asia/Riyadh')->format('Y-m-d H:i');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    private static function buildSignatureBlock(string $label, ?string $relativePath, string $meta): string
    {
        $imageBlock = '<div class="signature-empty">Signature pending</div>';

        if ($relativePath) {
            $absolutePath = storage_path('app/public/' . ltrim($relativePath, '/'));

            if (is_file($absolutePath)) {
                $imageSrc = 'file:///' . ltrim(str_replace('\\', '/', $absolutePath), '/');
                $imageBlock = '<img class="signature-image" src="' . e($imageSrc) . '" alt="' . e($label) . '" />';
            }
        }

        return '<div class="signature-label">' . e($label) . '</div>'
            . $imageBlock
            . '<div class="signature-meta">' . e($meta) . '</div>';
    }
}