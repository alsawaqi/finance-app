<?php

namespace App\Services\Twilio;

use App\Models\User;
use App\Services\Twilio\Concerns\InteractsWithTwilioWhatsApp;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;

/**
 * Bilingual WhatsApp confirmation after a client submits a new finance request (wizard).
 * Uses the phone from the submission form, falling back to the user's profile phone.
 */
final class ClientRequestSubmittedWhatsAppNotifier
{
    use InteractsWithTwilioWhatsApp;

    /**
     * @param  array<string, mixed>  $details  Validated `details` payload from StoreClientFinanceRequestRequest
     */
    public function notify(User $user, string $referenceNumber, array $details, ?string $acceptLanguageHeader = null): void
    {
        if (! $this->isFeatureEnabled()) {
            Log::notice('Request-submitted WhatsApp skipped: feature disabled in config.');

            return;
        }

        $referenceNumber = trim($referenceNumber);
        if ($referenceNumber === '') {
            return;
        }

        $e164 = $this->normalizeUserPhoneToE164($user->phone) ?? $this->normalizeFormPhoneToE164($details);
        if ($e164 === null) {
            Log::notice('Request-submitted WhatsApp skipped: no valid E.164 from user profile or form.', [
                'user_id' => $user->id,
                'finance_request_ref' => $referenceNumber,
            ]);

            return;
        }

        $client = $this->makeTwilioClient();
        if ($client === null) {
            Log::notice('Request-submitted WhatsApp skipped: Twilio credentials incomplete.');

            return;
        }

        $from = $this->resolveTwilioWhatsAppFrom();
        if ($from === null) {
            Log::notice('Request-submitted WhatsApp skipped: TWILIO_WHATSAPP_FROM missing or invalid (expect +E.164 or whatsapp:+…).');

            return;
        }

        if ($this->shouldUseLineTypeLookup() && ! $this->lineTypeAllowsWhatsAppAttempt($client, $e164)) {
            Log::info('Request-submitted WhatsApp skipped after Twilio Lookup line-type check.', [
                'user_id' => $user->id,
                'finance_request_ref' => $referenceNumber,
                'phone' => $this->maskPhone($e164),
            ]);

            return;
        }

        $body = $this->buildSubmittedBody($user->name, $referenceNumber, $acceptLanguageHeader);
        $to = 'whatsapp:'.$e164;

        try {
            $this->sendWhatsAppMessage($client, $to, $from, $body, [
                'context' => 'request_submitted',
                'user_id' => $user->id,
                'finance_request_ref' => $referenceNumber,
                'phone' => $this->maskPhone($e164),
            ]);
        } catch (RestException $e) {
            Log::warning('Twilio WhatsApp request-submitted confirmation failed.', [
                'user_id' => $user->id,
                'finance_request_ref' => $referenceNumber,
                'phone' => $this->maskPhone($e164),
                'twilio_code' => $e->getCode(),
                'twilio_message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Twilio WhatsApp request-submitted unexpected error.', [
                'user_id' => $user->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isFeatureEnabled(): bool
    {
        return (bool) config('services.twilio.request_submitted_whatsapp_enabled', true);
    }

    private function buildSubmittedBody(string $fullName, string $referenceNumber, ?string $acceptLanguageHeader): string
    {
        $brandEn = (string) config('services.twilio.brand_name_en', 'Nofa Cast');
        $brandAr = (string) config('services.twilio.brand_name_ar', 'نوفا كاست');
        $first = $this->firstNameOrFriendly($fullName);

        $en = "✅ Thank you, {$first}!\n\n"
            ."We've received your finance request at {$brandEn}.\n\n"
            ."📋 Reference: {$referenceNumber}\n"
            ."Please keep this reference handy for any follow-up with our team.\n\n"
            ."We're reviewing your submission and will get back to you as soon as possible.\n\n"
            ."— {$brandEn}";

        $ar = "✅ شكراً لك، {$first}!\n\n"
            ."استلمنا طلب التمويل الخاص بك في «{$brandAr}».\n\n"
            ."📋 رقم المرجع: {$referenceNumber}\n"
            ."احتفظ بهذا الرقم للمتابعة مع فريقنا.\n\n"
            ."نجري المراجعة وسنتواصل معك في أقرب وقت ممكن.\n\n"
            ."— {$brandAr}";

        return $this->mergeBilingualBody($en, $ar, $acceptLanguageHeader);
    }
}
