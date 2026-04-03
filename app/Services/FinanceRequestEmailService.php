<?php

namespace App\Services;

use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\RequestEmailDeliveryStatus;
use App\Enums\RequestEmailDirection;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestAgentAssignment;
use App\Models\RequestEmail;
use App\Models\RequestEmailAttachment;
use App\Models\User;
use App\Support\RequestTimelineLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class FinanceRequestEmailService
{
    public function __construct(
        private readonly UserMailboxMailerService $mailboxMailer,
    ) {
    }

    public function sendToAssignedAgent(FinanceRequest $financeRequest, User $sender, array $payload): RequestEmail
    {
        if (! filled($sender->email)) {
            throw ValidationException::withMessages([
                'sender' => 'The logged-in user does not have an email address saved yet.',
            ]);
        }

        $this->mailboxMailer->assertReady($sender);

        $stage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;
        if (! in_array($stage, [
            FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
            FinanceRequestWorkflowStage::PROCESSING->value,
        ], true)) {
            throw ValidationException::withMessages([
                'workflow_stage' => 'Request emails can only be sent after the admin has configured allowed bank agents.',
            ]);
        }

        $assignment = FinanceRequestAgentAssignment::query()
            ->with([
                'agent:id,name,email,bank_id',
                'bank:id,name,short_name,code',
                'allowedDocuments',
            ])
            ->where('finance_request_id', $financeRequest->id)
            ->where('agent_id', (int) $payload['agent_id'])
            ->where('is_active', true)
            ->first();

        if (! $assignment || ! $assignment->agent) {
            throw ValidationException::withMessages([
                'agent_id' => 'The selected agent is not assigned to this request.',
            ]);
        }

        if (($payload['bank_id'] ?? null) !== null) {
            $resolvedBankId = $assignment->bank_id ?: $assignment->agent->bank_id;
            if ((int) $resolvedBankId !== (int) $payload['bank_id']) {
                throw ValidationException::withMessages([
                    'bank_id' => 'The selected bank does not match the assigned bank for this agent.',
                ]);
            }
        }

        if (! filled($assignment->agent->email)) {
            throw ValidationException::withMessages([
                'agent_id' => 'The selected agent does not have an email address saved.',
            ]);
        }

        $allowedDocuments = $assignment->allowedDocuments
            ->keyBy('document_key');

        /** @var Collection<int, string> $documentKeys */
        $documentKeys = collect($payload['document_keys'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();

        if ($documentKeys->isEmpty()) {
            throw ValidationException::withMessages([
                'document_keys' => 'Please choose at least one approved attachment for this email.',
            ]);
        }

        $missingKey = $documentKeys->first(fn (string $key) => ! $allowedDocuments->has($key));
        if ($missingKey !== null) {
            throw ValidationException::withMessages([
                'document_keys' => "The file [{$missingKey}] is not allowed for the selected agent.",
            ]);
        }

        $selectedDocuments = $documentKeys
            ->map(fn (string $key) => $allowedDocuments->get($key))
            ->filter()
            ->values();

        $requestEmail = DB::transaction(function () use ($financeRequest, $sender, $assignment, $payload, $selectedDocuments) {
            $requestEmail = RequestEmail::create([
                'finance_request_id' => $financeRequest->id,
                'direction' => RequestEmailDirection::OUTBOUND,
                'sent_by' => $sender->id,
                'subject' => (string) $payload['subject'],
                'body' => $payload['body'] ?? null,
                'provider_message_id' => null,
                'thread_key' => 'finance-request-' . $financeRequest->id,
                'delivery_status' => RequestEmailDeliveryStatus::QUEUED,
                'from_email' => $sender->email,
                'to_emails_json' => [$assignment->agent->email],
                'cc_emails_json' => [],
                'bcc_emails_json' => [],
                'sent_at' => null,
            ]);

            $requestEmail->agents()->sync([$assignment->agent_id]);

            foreach ($selectedDocuments as $document) {
                RequestEmailAttachment::create([
                    'request_email_id' => $requestEmail->id,
                    'file_name' => $document->file_name,
                    'file_path' => $document->file_path,
                    'disk' => $document->disk ?: 'public',
                    'mime_type' => $document->mime_type,
                    'file_extension' => $document->file_extension,
                    'file_size' => $document->file_size,
                ]);
            }

            return $requestEmail->fresh(['sender:id,name,email', 'agents.bank:id,name,short_name,code', 'attachments']);
        });

        try {
            $this->mailboxMailer->sendRenderedMessage(
                $sender,
                [[
                    'email' => (string) $assignment->agent->email,
                    'name' => $assignment->agent->name,
                ]],
                (string) $requestEmail->subject,
                'emails.finance-request-outbound',
                [
                    'bodyText' => $requestEmail->body,
                    'senderName' => $sender->smtpSenderName(),
                    'senderEmail' => (string) $sender->smtpSenderEmail(),
                    'agentName' => $assignment->agent->name,
                    'requestReference' => (string) ($financeRequest->approval_reference_number ?: $financeRequest->reference_number ?: ('Request #' . $financeRequest->id)),
                ],
                $requestEmail->attachments
                    ->map(fn (RequestEmailAttachment $attachment) => [
                        'disk' => $attachment->disk ?: 'public',
                        'path' => $attachment->file_path,
                        'file_name' => $attachment->file_name,
                        'mime_type' => $attachment->mime_type,
                    ])
                    ->all(),
            );

            $requestEmail->forceFill([
                'delivery_status' => RequestEmailDeliveryStatus::SENT,
                'sent_at' => now(),
            ])->save();

            $financeRequest->forceFill([
                'latest_activity_at' => now(),
            ])->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.email_sent',
                $sender->id,
                'Request email sent',
                'تم إرسال رسالة بريد للطلب',
                'An outbound request email was sent to ' . $assignment->agent->name . ' with ' . $requestEmail->attachments->count() . ' linked file(s).',
                'تم إرسال رسالة بريد للطلب إلى ' . $assignment->agent->name . ' مع ' . $requestEmail->attachments->count() . ' ملف(ات) مرتبطة.',
                [
                    'request_email_id' => $requestEmail->id,
                    'agent_id' => $assignment->agent_id,
                    'agent_name' => $assignment->agent->name,
                    'bank_id' => $assignment->bank_id,
                    'bank_name' => $assignment->bank?->name,
                    'subject' => $requestEmail->subject,
                    'delivery_status' => RequestEmailDeliveryStatus::SENT->value,
                    'attachments' => $requestEmail->attachments->map(fn (RequestEmailAttachment $attachment) => [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                    ])->values()->all(),
                ],
            );
        } catch (Throwable $exception) {
            $requestEmail->forceFill([
                'delivery_status' => RequestEmailDeliveryStatus::FAILED,
            ])->save();

            $financeRequest->forceFill([
                'latest_activity_at' => now(),
            ])->save();

            RequestTimelineLogger::log(
                $financeRequest,
                'request.email_failed',
                $sender->id,
                'Request email failed',
                'فشل إرسال رسالة البريد للطلب',
                'The outbound request email to ' . $assignment->agent->name . ' failed: ' . str($exception->getMessage())->limit(240)->toString(),
                'فشل إرسال رسالة البريد إلى ' . $assignment->agent->name . ': ' . str($exception->getMessage())->limit(240)->toString(),
                [
                    'request_email_id' => $requestEmail->id,
                    'agent_id' => $assignment->agent_id,
                    'agent_name' => $assignment->agent->name,
                    'subject' => $requestEmail->subject,
                    'delivery_status' => RequestEmailDeliveryStatus::FAILED->value,
                ],
            );

            throw ValidationException::withMessages([
                'email' => 'The email could not be sent with the current mail configuration. ' . $exception->getMessage(),
            ]);
        }

        return $requestEmail->fresh(['sender:id,name,email', 'agents.bank:id,name,short_name,code', 'attachments']);
    }
}
