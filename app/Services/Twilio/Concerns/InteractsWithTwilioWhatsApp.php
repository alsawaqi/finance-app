<?php

namespace App\Services\Twilio\Concerns;

use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

trait InteractsWithTwilioWhatsApp
{
    /**
     * Twilio requires From like whatsapp:+14155238886. Many .env examples omit the whatsapp: prefix.
     */
    protected function resolveTwilioWhatsAppFrom(): ?string
    {
        $raw = trim((string) config('services.twilio.whatsapp_from', ''));
        if ($raw === '') {
            return null;
        }

        if (str_starts_with(strtolower($raw), 'whatsapp:')) {
            return $raw;
        }

        $normalized = preg_replace('/\s+/u', '', $raw);
        if (str_starts_with($normalized, '+')) {
            $core = preg_replace('/[^\d+]/', '', $normalized);
            if ($core !== '' && preg_match('/^\+\d{8,15}$/', $core)) {
                return 'whatsapp:'.$core;
            }

            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits !== '' && strlen($digits) >= 8 && strlen($digits) <= 15) {
            return 'whatsapp:+'.$digits;
        }

        return null;
    }

    protected function makeTwilioClient(): ?Client
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        if (empty($sid) || empty($token)) {
            return null;
        }

        return new Client((string) $sid, (string) $token);
    }

    /**
     * Normalize stored user phone (e.g. "+966 501234567") to E.164 for whatsapp:+...
     */
    protected function normalizeUserPhoneToE164(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $raw = trim($phone);

        // Preferred stored pattern in this app: "+<country_code> <local_number>".
        // Strip national trunk zeros from local part to keep canonical E.164.
        if (preg_match('/^\+(\d{1,4})[\s\-\(\)]+(.+)$/u', $raw, $matches)) {
            $countryCode = $matches[1] ?? '';
            $localDigits = preg_replace('/\D+/', '', (string) ($matches[2] ?? ''));
            $localDigits = ltrim((string) $localDigits, '0');

            if ($countryCode !== '' && $localDigits !== '') {
                $candidate = '+'.$countryCode.$localDigits;
                if (preg_match('/^\+\d{8,15}$/', $candidate)) {
                    return $candidate;
                }
            }
        }

        $compact = preg_replace('/\s+/u', '', $raw);
        if (! is_string($compact) || $compact === '') {
            return null;
        }

        if (str_starts_with($compact, '+')) {
            $digits = preg_replace('/[^\d+]/', '', $compact);
            if (is_string($digits) && preg_match('/^\+\d{8,15}$/', $digits)) {
                return $digits;
            }

            return null;
        }

        $onlyDigits = preg_replace('/\D+/', '', $compact);
        if ($onlyDigits !== '' && strlen($onlyDigits) >= 8 && strlen($onlyDigits) <= 15) {
            return '+'.$onlyDigits;
        }

        return null;
    }

    /**
     * Build E.164 from wizard/client form fields phone_country_code + phone_number.
     */
    protected function normalizeFormPhoneToE164(array $details): ?string
    {
        $ccDigits = preg_replace('/\D+/', '', (string) ($details['phone_country_code'] ?? ''));
        $numDigits = preg_replace('/\D+/', '', (string) ($details['phone_number'] ?? ''));
        $numDigits = ltrim($numDigits, '0');
        if ($ccDigits === '' || $numDigits === '') {
            return null;
        }

        $e164 = '+'.$ccDigits.$numDigits;
        if (! preg_match('/^\+\d{8,15}$/', $e164)) {
            return null;
        }

        return $e164;
    }

    protected function maskPhone(string $e164): string
    {
        if (strlen($e164) <= 6) {
            return '***';
        }

        return substr($e164, 0, 4).'****'.substr($e164, -2);
    }

    protected function shouldUseLineTypeLookup(): bool
    {
        return (bool) config('services.twilio.registration_lookup_line_type', false);
    }

    /**
     * When Lookup line_type_intelligence is enabled: skip landline / toll-free style numbers.
     */
    protected function lineTypeAllowsWhatsAppAttempt(Client $client, string $e164): bool
    {
        try {
            $result = $client->lookups->v2->phoneNumbers($e164)->fetch([
                'fields' => 'line_type_intelligence',
            ]);
        } catch (\Throwable $e) {
            Log::notice('Twilio Lookup line_type_intelligence failed; attempting WhatsApp send anyway.', [
                'phone' => $this->maskPhone($e164),
                'message' => $e->getMessage(),
            ]);

            return true;
        }

        if (! ($result->valid ?? false)) {
            return false;
        }

        $intel = $result->lineTypeIntelligence ?? null;
        if ($intel === null || $intel === '') {
            return true;
        }

        if (is_string($intel)) {
            $decoded = json_decode($intel, true);
            $intel = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($intel)) {
            return true;
        }

        $type = strtolower((string) ($intel['type'] ?? ''));
        $typeKey = str_replace([' ', '_', '-'], '', $type);

        $unlikelyWhatsApp = ['landline', 'tollfree', 'premium', 'pager', 'voicemail', 'sharedcost', 'uan'];

        return ! in_array($typeKey, $unlikelyWhatsApp, true);
    }

    protected function firstNameOrFriendly(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'there';
        }

        $parts = preg_split('/\s+/u', $name, 2);

        return $parts[0] ?? $name;
    }

    protected function mergeBilingualBody(string $en, string $ar, ?string $acceptLanguageHeader): string
    {
        $locale = $acceptLanguageHeader !== null && $acceptLanguageHeader !== ''
            ? strtolower(trim(explode(',', $acceptLanguageHeader)[0]))
            : '';

        if (str_starts_with($locale, 'ar')) {
            return $ar."\n\n───────────────\n\n".$en;
        }

        return $en."\n\n───────────────\n\n".$ar;
    }

    /**
     * Send via Twilio WhatsApp using Content Template when contentVariables are provided
     * and a template SID is configured. Falls back to plain body otherwise.
     *
     * @param  array<string, string>  $contentVariables  e.g. ['1' => 'Name', '2' => 'REF-123']
     * @param  array<string, mixed>   $context           Logging context
     *
     * @throws RestException
     */
    protected function sendWhatsAppMessage(
        Client $client,
        string $to,
        string $from,
        string $body,
        array $context = [],
        array $contentVariables = [],
    ): void {
        $templateSid = trim((string) config('services.twilio.template_sid', ''));
        $useTemplate = $templateSid !== '' && $contentVariables !== [];

        if ($useTemplate) {
            $payload = [
                'from' => $from,
                'contentSid' => $templateSid,
                'contentVariables' => json_encode($contentVariables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        } else {
            $payload = [
                'from' => $from,
                'body' => $body,
            ];
        }

        try {
            $client->messages->create($to, $payload);

            return;
        } catch (RestException $exception) {
            if (! $useTemplate || ! $this->shouldFallbackFromTemplateToBody()) {
                throw $exception;
            }

            Log::warning('Twilio WhatsApp template send failed; retrying with plain body.', [
                ...$context,
                'twilio_code' => $exception->getCode(),
                'twilio_message' => $exception->getMessage(),
            ]);
        }

        $client->messages->create($to, [
            'from' => $from,
            'body' => $body,
        ]);
    }

    private function shouldFallbackFromTemplateToBody(): bool
    {
        $value = config('services.twilio.template_fallback_to_body', true);
        if (is_bool($value)) {
            return $value;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }
}
