<?php

namespace App\Services\Twilio;

use App\Models\FinanceRequest;
use App\Models\RequestTimeline;
use App\Models\User;
use App\Services\Twilio\Concerns\InteractsWithTwilioWhatsApp;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;

/**
 * Bilingual WhatsApp updates for client-facing request stages that require client action.
 */
final class ClientStageWhatsAppNotifier
{
    use InteractsWithTwilioWhatsApp;

    public function notifyFromTimeline(FinanceRequest $financeRequest, RequestTimeline $timeline): void
    {
        if (! $this->isFeatureEnabled()) {
            return;
        }

        $eventType = (string) $timeline->event_type;
        $metadata = is_array($timeline->metadata_json) ? $timeline->metadata_json : [];

        $payload = $this->buildMessagePayload($financeRequest, $eventType, $metadata);
        if ($payload === null) {
            return;
        }

        $clientUser = $this->resolveClientUser($financeRequest);
        if (! $clientUser) {
            return;
        }

        $e164 = $this->normalizeUserPhoneToE164($clientUser->phone);
        if ($e164 === null) {
            $details = is_array($financeRequest->intake_details_json) ? $financeRequest->intake_details_json : [];
            $e164 = $this->normalizeFormPhoneToE164($details);
        }

        if ($e164 === null) {
            Log::notice('Client-stage WhatsApp skipped: no valid client phone.', [
                'request_id' => $financeRequest->id,
                'event_type' => $eventType,
            ]);

            return;
        }

        $client = $this->makeTwilioClient();
        if ($client === null) {
            Log::notice('Client-stage WhatsApp skipped: Twilio credentials incomplete.');

            return;
        }

        $from = $this->resolveTwilioWhatsAppFrom();
        if ($from === null) {
            Log::notice('Client-stage WhatsApp skipped: TWILIO_WHATSAPP_FROM missing or invalid.');

            return;
        }

        if ($this->shouldUseLineTypeLookup() && ! $this->lineTypeAllowsWhatsAppAttempt($client, $e164)) {
            Log::info('Client-stage WhatsApp skipped after Twilio Lookup line-type check.', [
                'request_id' => $financeRequest->id,
                'event_type' => $eventType,
                'phone' => $this->maskPhone($e164),
            ]);

            return;
        }

        $body = $this->mergeBilingualBody($payload['en'], $payload['ar'], null);
        $to = 'whatsapp:'.$e164;

        try {
            $this->sendWhatsAppMessage($client, $to, $from, $body, [
                'context' => 'client_stage',
                'request_id' => $financeRequest->id,
                'event_type' => $eventType,
                'phone' => $this->maskPhone($e164),
            ]);
        } catch (RestException $e) {
            Log::warning('Client-stage WhatsApp send failed.', [
                'request_id' => $financeRequest->id,
                'event_type' => $eventType,
                'phone' => $this->maskPhone($e164),
                'twilio_code' => $e->getCode(),
                'twilio_message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Client-stage WhatsApp unexpected error.', [
                'request_id' => $financeRequest->id,
                'event_type' => $eventType,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isFeatureEnabled(): bool
    {
        return (bool) config('services.twilio.client_stage_whatsapp_enabled', true);
    }

    private function resolveClientUser(FinanceRequest $financeRequest): ?User
    {
        if (! $financeRequest->user_id) {
            return null;
        }

        return User::query()
            ->where('id', (int) $financeRequest->user_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{en: string, ar: string}|null
     */
    private function buildMessagePayload(FinanceRequest $financeRequest, string $eventType, array $metadata): ?array
    {
        $requestRef = (string) ($financeRequest->reference_number ?: ('REQ-'.$financeRequest->id));
        $approvalRef = trim((string) ($financeRequest->approval_reference_number ?: $requestRef));
        $brandEn = (string) config('services.twilio.brand_name_en', 'Nofa Cast');
        $brandAr = (string) config('services.twilio.brand_name_ar', $this->decodeUnicode('\\u0646\\u0648\\u0641\\u0627 \\u0643\\u0627\\u0633\\u062a'));

        if ($eventType === 'contract.admin_signed') {
            return [
                'en' => "Hello,\n\nYour contract is ready for signature.\nApproval number: {$approvalRef}\nPlease sign it in your client portal to continue your request.\n\n- {$brandEn}",
                'ar' => $this->decodeUnicode("\\u0645\\u0631\\u062d\\u0628\\u0627\\u064b\\u060c\\n\\n\\u0627\\u0644\\u0639\\u0642\\u062f \\u062c\\u0627\\u0647\\u0632 \\u0644\\u0644\\u062a\\u0648\\u0642\\u064a\\u0639.\\n\\u0631\\u0642\\u0645 \\u0627\\u0644\\u0645\\u0648\\u0627\\u0641\\u0642\\u0629: {$approvalRef}\\n\\u064a\\u0631\\u062c\\u0649 \\u062a\\u0648\\u0642\\u064a\\u0639\\u0647 \\u0645\\u0646 \\u0628\\u0648\\u0627\\u0628\\u0629 \\u0627\\u0644\\u0639\\u0645\\u064a\\u0644 \\u0644\\u0645\\u062a\\u0627\\u0628\\u0639\\u0629 \\u0637\\u0644\\u0628\\u0643.\\n\\n- {$brandAr}"),
            ];
        }

        if ($eventType === 'request.required_document_change_requested') {
            $documentName = trim((string) ($metadata['document_name'] ?? 'required document'));

            return [
                'en' => "Hello,\n\nA correction is required for one of your uploaded documents.\nDocument: {$documentName}\nApproval number: {$approvalRef}\nPlease upload the corrected file from your documents page.\n\n- {$brandEn}",
                'ar' => $this->decodeUnicode("\\u0645\\u0631\\u062d\\u0628\\u0627\\u064b\\u060c\\n\\n\\u064a\\u0648\\u062c\\u062f \\u0637\\u0644\\u0628 \\u062a\\u0639\\u062f\\u064a\\u0644 \\u0644\\u0623\\u062d\\u062f \\u0627\\u0644\\u0645\\u0633\\u062a\\u0646\\u062f\\u0627\\u062a \\u0627\\u0644\\u0645\\u0631\\u0641\\u0648\\u0639\\u0629.\\n\\u0627\\u0644\\u0645\\u0633\\u062a\\u0646\\u062f: {$documentName}\\n\\u0631\\u0642\\u0645 \\u0627\\u0644\\u0645\\u0648\\u0627\\u0641\\u0642\\u0629: {$approvalRef}\\n\\u064a\\u0631\\u062c\\u0649 \\u0631\\u0641\\u0639 \\u0627\\u0644\\u0646\\u0633\\u062e\\u0629 \\u0627\\u0644\\u0645\\u0635\\u062d\\u062d\\u0629 \\u0645\\u0646 \\u0635\\u0641\\u062d\\u0629 \\u0627\\u0644\\u0645\\u0633\\u062a\\u0646\\u062f\\u0627\\u062a.\\n\\n- {$brandAr}"),
            ];
        }

        if ($eventType === 'request.additional_document_requested') {
            $title = trim((string) ($metadata['title'] ?? 'additional document'));

            return [
                'en' => "Hello,\n\nAn additional document has been requested.\nDocument: {$title}\nApproval number: {$approvalRef}\nPlease upload it from your request documents page.\n\n- {$brandEn}",
                'ar' => $this->decodeUnicode("\\u0645\\u0631\\u062d\\u0628\\u0627\\u064b\\u060c\\n\\n\\u062a\\u0645 \\u0637\\u0644\\u0628 \\u0645\\u0633\\u062a\\u0646\\u062f \\u0625\\u0636\\u0627\\u0641\\u064a.\\n\\u0627\\u0644\\u0645\\u0633\\u062a\\u0646\\u062f: {$title}\\n\\u0631\\u0642\\u0645 \\u0627\\u0644\\u0645\\u0648\\u0627\\u0641\\u0642\\u0629: {$approvalRef}\\n\\u064a\\u0631\\u062c\\u0649 \\u0631\\u0641\\u0639\\u0647 \\u0645\\u0646 \\u0635\\u0641\\u062d\\u0629 \\u0645\\u0633\\u062a\\u0646\\u062f\\u0627\\u062a \\u0627\\u0644\\u0637\\u0644\\u0628.\\n\\n- {$brandAr}"),
            ];
        }

        if ($eventType === 'request.client_update_requested') {
            return [
                'en' => "Hello,\n\nYour request needs updates.\nApproval number: {$approvalRef}\nPlease review the requested changes and submit the required updates from your portal.\n\n- {$brandEn}",
                'ar' => $this->decodeUnicode("\\u0645\\u0631\\u062d\\u0628\\u0627\\u064b\\u060c\\n\\n\\u064a\\u0648\\u062c\\u062f \\u0637\\u0644\\u0628 \\u062a\\u062d\\u062f\\u064a\\u062b \\u0639\\u0644\\u0649 \\u0628\\u064a\\u0627\\u0646\\u0627\\u062a\\u0643.\\n\\u0631\\u0642\\u0645 \\u0627\\u0644\\u0645\\u0648\\u0627\\u0641\\u0642\\u0629: {$approvalRef}\\n\\u064a\\u0631\\u062c\\u0649 \\u0645\\u0631\\u0627\\u062c\\u0639\\u0629 \\u0627\\u0644\\u0637\\u0644\\u0628\\u0627\\u062a \\u0648\\u0625\\u0631\\u0633\\u0627\\u0644 \\u0627\\u0644\\u062a\\u062d\\u062f\\u064a\\u062b\\u0627\\u062a \\u0627\\u0644\\u0645\\u0637\\u0644\\u0648\\u0628\\u0629 \\u0645\\u0646 \\u0627\\u0644\\u0628\\u0648\\u0627\\u0628\\u0629.\\n\\n- {$brandAr}"),
            ];
        }

        if ($eventType === 'request.final_approved') {
            return [
                'en' => "Hello,\n\nYour request has been approved.\nApproval number: {$approvalRef}\nStatus: Completed.\nYou can review the final details from your client portal.\n\n- {$brandEn}",
                'ar' => $this->decodeUnicode("\\u0645\\u0631\\u062d\\u0628\\u0627\\u064b\\u060c\\n\\n\\u062a\\u0645\\u062a \\u0627\\u0644\\u0645\\u0648\\u0627\\u0641\\u0642\\u0629 \\u0639\\u0644\\u0649 \\u0637\\u0644\\u0628\\u0643.\\n\\u0631\\u0642\\u0645 \\u0627\\u0644\\u0645\\u0648\\u0627\\u0641\\u0642\\u0629: {$approvalRef}\\n\\u0627\\u0644\\u062d\\u0627\\u0644\\u0629: \\u0645\\u0643\\u062a\\u0645\\u0644.\\n\\u064a\\u0645\\u0643\\u0646\\u0643 \\u0645\\u0631\\u0627\\u062c\\u0639\\u0629 \\u0627\\u0644\\u062a\\u0641\\u0627\\u0635\\u064a\\u0644 \\u0627\\u0644\\u0646\\u0647\\u0627\\u0626\\u064a\\u0629 \\u0645\\u0646 \\u0628\\u0648\\u0627\\u0628\\u0629 \\u0627\\u0644\\u0639\\u0645\\u064a\\u0644.\\n\\n- {$brandAr}"),
            ];
        }

        if ($eventType === 'request.rejected') {
            return [
                'en' => "Hello,\n\nYour finance request was not approved.\nApproval number: {$approvalRef}\nYou can review the latest notes in your request details.\n\n- {$brandEn}",
                'ar' => $this->decodeUnicode("\\u0645\\u0631\\u062d\\u0628\\u0627\\u064b\\u060c\\n\\n\\u062a\\u0645 \\u0631\\u0641\\u0636 \\u0637\\u0644\\u0628 \\u0627\\u0644\\u062a\\u0645\\u0648\\u064a\\u0644.\\n\\u0631\\u0642\\u0645 \\u0627\\u0644\\u0645\\u0648\\u0627\\u0641\\u0642\\u0629: {$approvalRef}\\n\\u064a\\u0645\\u0643\\u0646\\u0643 \\u0645\\u0631\\u0627\\u062c\\u0639\\u0629 \\u0622\\u062e\\u0631 \\u0627\\u0644\\u0645\\u0644\\u0627\\u062d\\u0638\\u0627\\u062a \\u0641\\u064a \\u062a\\u0641\\u0627\\u0635\\u064a\\u0644 \\u0627\\u0644\\u0637\\u0644\\u0628.\\n\\n- {$brandAr}"),
            ];
        }

        if ($eventType === 'contract.client_signed' && ($metadata['requires_commercial_registration'] ?? false)) {
            return [
                'en' => "Hello,\n\nThank you for signing your contract.\nApproval number: {$approvalRef}\nNext step: please upload the Chamber of Commerce authenticated contract from your portal.\n\n- {$brandEn}",
                'ar' => $this->decodeUnicode("\\u0645\\u0631\\u062d\\u0628\\u0627\\u064b\\u060c\\n\\n\\u0634\\u0643\\u0631\\u0627\\u064b \\u0644\\u062a\\u0648\\u0642\\u064a\\u0639 \\u0627\\u0644\\u0639\\u0642\\u062f.\\n\\u0631\\u0642\\u0645 \\u0627\\u0644\\u0645\\u0648\\u0627\\u0641\\u0642\\u0629: {$approvalRef}\\n\\u0627\\u0644\\u062e\\u0637\\u0648\\u0629 \\u0627\\u0644\\u062a\\u0627\\u0644\\u064a\\u0629: \\u064a\\u0631\\u062c\\u0649 \\u0631\\u0641\\u0639 \\u0627\\u0644\\u0639\\u0642\\u062f \\u0627\\u0644\\u0645\\u0648\\u062b\\u0642 \\u0645\\u0646 \\u0627\\u0644\\u063a\\u0631\\u0641\\u0629 \\u0627\\u0644\\u062a\\u062c\\u0627\\u0631\\u064a\\u0629 \\u0639\\u0628\\u0631 \\u0627\\u0644\\u0628\\u0648\\u0627\\u0628\\u0629.\\n\\n- {$brandAr}"),
            ];
        }

        return null;
    }

    private function decodeUnicode(string $value): string
    {
        $decoded = json_decode('"'.$value.'"', true);

        return is_string($decoded) ? $decoded : $value;
    }
}
