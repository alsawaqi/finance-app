<?php

namespace App\Support;

use App\Models\ContractTemplate;
use App\Models\FinanceRequest;
use Illuminate\Support\Arr;

class ContractTemplateResolver
{
    public static function resolveTemplateForRequest(FinanceRequest $financeRequest): ContractTemplate
    {
        $slug = self::defaultSlugForRequest($financeRequest);

        return ContractTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->orderByDesc('version_no')
            ->firstOrFail();
    }

    public static function defaultSlugForRequest(FinanceRequest $financeRequest): string
    {
        $applicantType = strtolower((string) ($financeRequest->applicant_type ?: Arr::get($financeRequest->intake_details_json ?? [], 'finance_type', 'individual')));

        return $applicantType === 'company'
            ? 'finance-company-ar'
            : 'finance-individual-ar';
    }

    public static function renderEditableHtml(FinanceRequest $financeRequest, ?ContractTemplate $template = null): string
    {
        $template ??= self::resolveTemplateForRequest($financeRequest);

        $html = strtr((string) $template->template_content, self::placeholderMap($financeRequest));

        return (string) preg_replace('/\{\{[^}]+\}\}/u', '—', $html);
    }

    public static function placeholderMap(FinanceRequest $financeRequest): array
    {
        $details = (array) ($financeRequest->intake_details_json ?? []);

        $applicantName = trim((string) ($details['full_name'] ?? $details['name'] ?? $financeRequest->client?->name ?? ''));
        $companyName = trim((string) ($financeRequest->company_name ?: Arr::get($details, 'company_name', '')));
        $unifiedNumber = trim((string) Arr::get($details, 'unified_number', ''));
        $nationalAddressNumber = trim((string) Arr::get($details, 'national_address_number', ''));
        $companyCrNumber = trim((string) Arr::get($details, 'company_cr_number', ''));
        $address = trim((string) Arr::get($details, 'address', ''));
        $email = trim((string) Arr::get($details, 'email', $financeRequest->client?->email ?? ''));
        $phoneCountryCode = trim((string) Arr::get($details, 'phone_country_code', ''));
        $phoneNumber = trim((string) Arr::get($details, 'phone_number', $financeRequest->client?->phone ?? ''));
        $phoneDisplay = trim(implode(' ', array_filter([$phoneCountryCode, $phoneNumber])));
        $requestedAmount = trim((string) Arr::get($details, 'requested_amount', ''));
        $notes = trim((string) Arr::get($details, 'notes', ''));
        $contractDate = now('Asia/Riyadh')->format('d/m/Y');
        $documentReference = trim((string) ($financeRequest->approval_reference_number ?: $financeRequest->reference_number ?: '—'));
        $companyOrApplicant = $companyName !== '' ? $companyName : $applicantName;

        $escape = static fn (?string $value, string $fallback = '—'): string => htmlspecialchars(
            trim((string) $value) !== '' ? trim((string) $value) : $fallback,
            ENT_QUOTES,
            'UTF-8'
        );

        return [
            '{{document_reference}}' => $escape($documentReference),
            '{{contract_date}}' => $escape($contractDate),
            '{{applicant_name}}' => $escape($applicantName),
            '{{company_name}}' => $escape($companyName),
            '{{company_or_applicant_name}}' => $escape($companyOrApplicant),
            '{{representative_name}}' => $escape($applicantName),
            '{{representative_role}}' => $escape('مقدم الطلب'),
            '{{unified_number}}' => $escape($unifiedNumber),
            '{{national_address_number}}' => $escape($nationalAddressNumber),
            '{{company_cr_number}}' => $escape($companyCrNumber),
            '{{address}}' => $escape($address),
            '{{email}}' => $escape($email),
            '{{phone_display}}' => $escape($phoneDisplay),
            '{{requested_amount}}' => $escape($requestedAmount),
            '{{notes}}' => $escape($notes, 'لا توجد ملاحظات إضافية.'),
        ];
    }
}
