<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FinanceRequestOutboundEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{disk:string,path:string,file_name:string,mime_type:?string}>  $attachmentsData
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly ?string $bodyText,
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $agentName,
        public readonly string $requestReference,
        public readonly array $attachmentsData = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.finance-request-outbound',
            with: [
                'bodyText' => $this->bodyText,
                'senderName' => $this->senderName,
                'senderEmail' => $this->senderEmail,
                'agentName' => $this->agentName,
                'requestReference' => $this->requestReference,
            ],
        );
    }

    public function attachments(): array
    {
        return collect($this->attachmentsData)
            ->filter(fn (array $attachment) => filled($attachment['path'] ?? null))
            ->map(function (array $attachment) {
                $mailAttachment = Attachment::fromStorageDisk(
                    $attachment['disk'] ?? 'public',
                    $attachment['path'],
                )->as($attachment['file_name'] ?? basename((string) $attachment['path']));

                if (filled($attachment['mime_type'] ?? null)) {
                    $mailAttachment = $mailAttachment->withMime((string) $attachment['mime_type']);
                }

                return $mailAttachment;
            })
            ->values()
            ->all();
    }
}
