<?php

namespace App\Services\Twilio;

use App\Models\User;
use App\Services\Twilio\Concerns\InteractsWithTwilioWhatsApp;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;

/**
 * Sends a bilingual welcome WhatsApp after client registration when a phone number is present.
 *
 * Twilio does not expose a reliable "has WhatsApp" pre-check; optional Lookup line-type
 * intelligence skips obvious non-mobile lines (e.g. landline). Send failures are logged only.
 */
final class RegistrationWelcomeWhatsAppNotifier
{
    use InteractsWithTwilioWhatsApp;

    public function notify(User $user, ?string $acceptLanguageHeader = null): void
    {
        if (! $this->isFeatureEnabled()) {
            Log::notice('Registration WhatsApp welcome skipped: feature disabled in config.');

            return;
        }

        $e164 = $this->normalizeUserPhoneToE164($user->phone);
        if ($e164 === null) {
            Log::notice('Registration WhatsApp welcome skipped: user phone missing or not valid E.164.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $client = $this->makeTwilioClient();
        if ($client === null) {
            Log::notice('Registration WhatsApp welcome skipped: Twilio credentials incomplete.');

            return;
        }

        $from = $this->resolveTwilioWhatsAppFrom();
        if ($from === null) {
            Log::notice('Registration WhatsApp welcome skipped: TWILIO_WHATSAPP_FROM missing or invalid (expect +E.164 or whatsapp:+…).');

            return;
        }

        if ($this->shouldUseLineTypeLookup() && ! $this->lineTypeAllowsWhatsAppAttempt($client, $e164)) {
            Log::info('Registration WhatsApp welcome skipped after Twilio Lookup line-type check.', [
                'user_id' => $user->id,
                'phone' => $this->maskPhone($e164),
            ]);

            return;
        }

        $body = $this->buildWelcomeBody($user->name, $acceptLanguageHeader);
        $to = 'whatsapp:'.$e164;

        try {
            $this->sendWhatsAppMessage($client, $to, $from, $body, [
                'context' => 'registration_welcome',
                'user_id' => $user->id,
                'phone' => $this->maskPhone($e164),
            ]);
        } catch (RestException $e) {
            Log::warning('Twilio WhatsApp registration welcome failed.', [
                'user_id' => $user->id,
                'phone' => $this->maskPhone($e164),
                'twilio_code' => $e->getCode(),
                'twilio_message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Twilio WhatsApp registration welcome unexpected error.', [
                'user_id' => $user->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isFeatureEnabled(): bool
    {
        return (bool) config('services.twilio.registration_welcome_enabled', true);
    }

    private function buildWelcomeBody(string $fullName, ?string $acceptLanguageHeader): string
    {
        $brandEn = (string) config('services.twilio.brand_name_en', 'Nofa Cast');
        $brandAr = (string) config('services.twilio.brand_name_ar', 'نوفا كاست');
        $first = $this->firstNameOrFriendly($fullName);

        $en = "🎉 Welcome to {$brandEn}, {$first}!\n\n"
            ."We're thrilled you've joined us. You can sign in anytime to submit and track your finance requests securely.\n\n"
            .'✨ Our team is here if you need help along the way.';

        $ar = "🎉 أهلاً وسهلاً، {$first}!\n\n"
            ."نورتَ منصّة «{$brandAr}». يمكنك الآن تسجيل الدخول ومتابعة طلبات التمويل بسهولة وأمان.\n\n"
            .'✨ فريقنا جاهز لمساعدتك في أي وقت.';

        return $this->mergeBilingualBody($en, $ar, $acceptLanguageHeader);
    }
}
