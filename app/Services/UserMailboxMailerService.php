<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class UserMailboxMailerService
{
    public function __construct(
        private readonly ViewFactory $views,
    ) {
    }

    public function summary(User $user): array
    {
        return [
            ...$user->mailboxSettingsSummary(),
            'smtp_host' => (string) config('mail.mailers.smtp.host'),
            'smtp_port' => (int) config('mail.mailers.smtp.port'),
            'smtp_encryption' => (string) (config('mail.mailers.smtp.scheme') ?: config('mail.mailers.smtp.encryption') ?: ''),
            'default_sender_email' => $user->email,
        ];
    }

    public function assertReady(User $user): void
    {
        if (! filled($user->smtpSenderEmail())) {
            throw ValidationException::withMessages([
                'mailbox' => 'The admin still needs to save the mailbox email/username for this staff account before request emails can be sent.',
            ]);
        }

        if (! $user->hasStoredSmtpPassword()) {
            throw ValidationException::withMessages([
                'mailbox' => 'The admin still needs to save the Hostinger mailbox password for this staff account before request emails can be sent.',
            ]);
        }

        if (! $user->hasVerifiedMailboxSettings()) {
            throw ValidationException::withMessages([
                'mailbox' => 'The admin still needs to verify this mailbox from the Mail Settings page before request emails can be sent.',
            ]);
        }
    }

    /**
     * @param  array<int, array{email:string,name:?string}>  $recipients
     * @param  array<int, array{disk:string,path:string,file_name:string,mime_type:?string}>  $attachments
     */
    public function sendRenderedMessage(
        User $sender,
        array $recipients,
        string $subject,
        string $view,
        array $viewData = [],
        array $attachments = [],
    ): void {
        $html = $this->views->make($view, $viewData)->render();
        $text = trim(Str::of($html)->replace(['<br>', '<br/>', '<br />'], "
")->stripTags()->toString());

        $email = $this->buildBaseEmail($sender, $subject, $html, $text);

        foreach ($recipients as $recipient) {
            if (! filled($recipient['email'] ?? null)) {
                continue;
            }

            $email->addTo(new Address((string) $recipient['email'], (string) ($recipient['name'] ?? '')));
        }

        foreach ($attachments as $attachment) {
            $disk = (string) ($attachment['disk'] ?? 'public');
            $path = (string) ($attachment['path'] ?? '');

            if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                throw ValidationException::withMessages([
                    'attachments' => 'One of the selected request files could not be found in storage.',
                ]);
            }

            $email->attach(
                Storage::disk($disk)->get($path),
                (string) ($attachment['file_name'] ?? basename($path)),
                (string) ($attachment['mime_type'] ?? 'application/octet-stream'),
            );
        }

        $this->buildMailer($sender)->send($email);
    }

    public function sendTestMessage(User $sender): void
    {
        $senderEmail = $sender->smtpSenderEmail();

        if (! filled($senderEmail)) {
            throw ValidationException::withMessages([
                'smtp_username' => 'Please save the mailbox email before testing.',
            ]);
        }

        if (! $sender->hasStoredSmtpPassword()) {
            throw ValidationException::withMessages([
                'smtp_password' => 'Please save the mailbox password before testing.',
            ]);
        }

        $html = $this->views->make('emails.user-mailbox-test', [
            'senderName' => $sender->smtpSenderName(),
            'senderEmail' => $senderEmail,
            'testedAt' => now()->toDayDateTimeString(),
        ])->render();

        $text = "Mailbox test successful.\nSender: {$sender->smtpSenderName()} <{$senderEmail}>\nSent at: " . now()->toDayDateTimeString();

        $email = $this->buildBaseEmail(
            $sender,
            'Finance mailbox test',
            $html,
            $text,
        );

        $email->addTo(new Address($senderEmail, $sender->smtpSenderName()));

        $this->buildMailer($sender)->send($email);
    }

    private function buildBaseEmail(User $sender, string $subject, string $html, string $text): Email
    {
        $senderEmail = $sender->smtpSenderEmail();

        if (! filled($senderEmail)) {
            throw ValidationException::withMessages([
                'mailbox' => 'The current user does not have a valid mailbox email configured.',
            ]);
        }

        return (new Email())
            ->subject($subject)
            ->from(new Address($senderEmail, $sender->smtpSenderName()))
            ->replyTo(new Address($senderEmail, $sender->smtpSenderName()))
            ->html($html)
            ->text($text);
    }

    private function buildMailer(User $sender): Mailer
    {
        $username = $sender->smtpSenderEmail();
        $password = (string) $sender->smtp_password;
        $host = (string) config('mail.mailers.smtp.host', 'smtp.hostinger.com');
        $port = (int) config('mail.mailers.smtp.port', 465);
        $scheme = $this->resolveScheme();

        $query = [];
        if ($scheme === 'smtp' && filled(config('mail.mailers.smtp.encryption'))) {
            $query['encryption'] = (string) config('mail.mailers.smtp.encryption');
        }
        if (filled(config('mail.mailers.smtp.local_domain'))) {
            $query['local_domain'] = (string) config('mail.mailers.smtp.local_domain');
        }

        $dsn = sprintf(
            '%s://%s:%s@%s:%d%s',
            $scheme,
            rawurlencode((string) $username),
            rawurlencode($password),
            $host,
            $port,
            $query ? '?' . http_build_query($query) : '',
        );

        return new Mailer(Transport::fromDsn($dsn));
    }

    private function resolveScheme(): string
    {
        $configuredScheme = trim((string) config('mail.mailers.smtp.scheme', ''));
        if ($configuredScheme !== '') {
            return $configuredScheme;
        }

        $encryption = strtolower(trim((string) config('mail.mailers.smtp.encryption', '')));

        return $encryption === 'ssl' ? 'smtps' : 'smtp';
    }
}
