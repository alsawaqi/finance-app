<?php

namespace App\Services;

use App\Models\MailboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MailboxSyncService
{
    public function syncInbox(User $user, ?string $folder = null, ?int $limit = null): array
    {
        $this->ensureImapExtension();
        $this->ensureMailboxReady($user);

        $folderName = $folder ?: (string) config('mailbox.imap_folder', 'INBOX');
        $syncLimit = $limit ?: (int) config('mailbox.imap_sync_limit', 40);

        $mailboxString = $this->buildMailboxString($folderName);
        $username = $user->smtpSenderEmail();
        $password = (string) $user->smtp_password;

        $stream = @imap_open(
            $mailboxString,
            $username,
            $password,
            0,
            1,
            ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
        );

        if (! $stream) {
            $error = imap_last_error() ?: 'Unable to connect to IMAP mailbox.';
            $user->forceFill([
                'inbox_last_error' => $error,
            ])->save();

            throw ValidationException::withMessages([
                'mailbox' => $error,
            ]);
        }

        $created = 0;
        $updated = 0;
        $attachmentsCreated = 0;

        try {
            $messageCount = imap_num_msg($stream);
            $start = max(1, $messageCount - $syncLimit + 1);

            for ($msgNo = $messageCount; $msgNo >= $start; $msgNo--) {
                $overviewRows = imap_fetch_overview($stream, (string) $msgNo, 0);
                $overview = $overviewRows[0] ?? null;
                $header = @imap_headerinfo($stream, $msgNo);
                $providerUid = (string) imap_uid($stream, $msgNo);

                if ($providerUid === '') {
                    continue;
                }

                $payload = $this->extractPayload($stream, $msgNo, $overview, $header);

                $message = MailboxMessage::query()->firstOrNew([
                    'user_id' => $user->id,
                    'folder_name' => $folderName,
                    'provider_uid' => $providerUid,
                ]);

                $isNew = ! $message->exists;

                $message->fill([
                    'message_id' => $payload['message_id'],
                    'in_reply_to' => $payload['in_reply_to'],
                    'references_header' => $payload['references_header'],
                    'subject' => $payload['subject'],
                    'from_email' => $payload['from_email'],
                    'from_name' => $payload['from_name'],
                    'to_emails_json' => $payload['to_emails_json'],
                    'cc_emails_json' => $payload['cc_emails_json'],
                    'body_text' => $payload['body_text'],
                    'body_html' => $payload['body_html'],
                    'received_at' => $payload['received_at'],
                    'is_read' => $payload['is_read'],
                    'has_attachments' => count($payload['attachments']) > 0,
                ]);

                $message->save();

                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }

                $attachmentsCreated += $this->replaceAttachments($message, $payload['attachments']);
            }

            $user->forceFill([
                'inbox_last_synced_at' => now(),
                'inbox_last_error' => null,
            ])->save();

            return [
                'created' => $created,
                'updated' => $updated,
                'attachments_created' => $attachmentsCreated,
            ];
        } catch (Throwable $exception) {
            $user->forceFill([
                'inbox_last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        } finally {
            imap_close($stream);
            imap_errors();
            imap_alerts();
        }
    }

    private function ensureImapExtension(): void
    {
        if (! function_exists('imap_open')) {
            throw ValidationException::withMessages([
                'mailbox' => 'PHP IMAP extension is not enabled on this server.',
            ]);
        }
    }

    private function ensureMailboxReady(User $user): void
    {
        if (! $user->hasVerifiedMailboxSettings()) {
            throw ValidationException::withMessages([
                'mailbox' => 'This staff mailbox is not verified yet. Ask the admin to complete mailbox setup first.',
            ]);
        }
    }

    private function buildMailboxString(string $folder): string
    {
        $host = (string) config('mailbox.imap_host', 'imap.hostinger.com');
        $port = (int) config('mailbox.imap_port', 993);
        $encryption = strtolower((string) config('mailbox.imap_encryption', 'ssl'));
        $validateCert = (bool) config('mailbox.imap_validate_cert', false);

        $flags = '/imap';

        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        if (! $validateCert) {
            $flags .= '/novalidate-cert';
        }

        return sprintf('{%s:%d%s}%s', $host, $port, $flags, $folder);
    }

    private function extractPayload($stream, int $msgNo, $overview, $header): array
    {
        $structure = @imap_fetchstructure($stream, $msgNo);

        $collector = [
            'text' => '',
            'html' => '',
            'attachments' => [],
        ];

        if ($structure) {
            $this->collectParts($stream, $msgNo, $structure, '', $collector);
        } else {
            $collector['text'] = (string) @imap_body($stream, $msgNo, FT_PEEK);
        }

        $from = $this->firstAddress($header?->from ?? []);
        $to = $this->addressList($header?->to ?? []);
        $cc = $this->addressList($header?->cc ?? []);

        $subject = $this->decodeMimeHeader((string) ($overview->subject ?? $header?->subject ?? ''));
        $messageId = $this->cleanHeaderIdentifier((string) ($overview->message_id ?? $header?->message_id ?? ''));
        $inReplyTo = $this->cleanHeaderIdentifier((string) ($header?->in_reply_to ?? ''));
        $references = trim((string) ($header?->references ?? ''));

        $receivedAt = null;
        if (! empty($overview->date)) {
            try {
                $receivedAt = \Illuminate\Support\Carbon::parse($overview->date);
            } catch (Throwable) {
                $receivedAt = null;
            }
        }

        return [
            'message_id' => $messageId !== '' ? $messageId : null,
            'in_reply_to' => $inReplyTo !== '' ? $inReplyTo : null,
            'references_header' => $references !== '' ? $references : null,
            'subject' => $subject !== '' ? $subject : '(No subject)',
            'from_email' => $from['email'] ?: null,
            'from_name' => $from['name'] ?: null,
            'to_emails_json' => $to,
            'cc_emails_json' => $cc,
            'body_text' => $collector['text'] !== '' ? $collector['text'] : strip_tags($collector['html']),
            'body_html' => $collector['html'] !== '' ? $collector['html'] : null,
            'received_at' => $receivedAt,
            'is_read' => (bool) ($overview->seen ?? false),
            'attachments' => $collector['attachments'],
        ];
    }

    private function collectParts($stream, int $msgNo, object $structure, string $partNumber, array &$collector): void
    {
        if (! empty($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $nextPartNumber = $partNumber === ''
                    ? (string) ($index + 1)
                    : $partNumber . '.' . ($index + 1);

                $this->collectParts($stream, $msgNo, $part, $nextPartNumber, $collector);
            }

            return;
        }

        $content = $this->fetchPartContent($stream, $msgNo, $partNumber, $structure);
        $type = (int) ($structure->type ?? 0);
        $subtype = strtolower((string) ($structure->subtype ?? ''));
        $fileName = $this->extractAttachmentName($structure);
        $contentId = $this->cleanHeaderIdentifier((string) ($structure->id ?? ''));
        $isAttachment = $fileName !== null || $this->hasAttachmentDisposition($structure);

        if ($isAttachment) {
            $collector['attachments'][] = [
                'file_name' => $fileName ?: ('attachment-' . Str::random(8)),
                'content' => $content,
                'mime_type' => $this->resolveMimeType($type, $subtype),
                'content_id' => $contentId !== '' ? $contentId : null,
            ];

            return;
        }

        if ($type === 0 && $subtype === 'plain' && trim($collector['text']) === '') {
            $collector['text'] = trim($content);
            return;
        }

        if ($type === 0 && $subtype === 'html' && trim($collector['html']) === '') {
            $collector['html'] = trim($content);
        }
    }

    private function fetchPartContent($stream, int $msgNo, string $partNumber, object $structure): string
    {
        $raw = $partNumber === ''
            ? (string) @imap_body($stream, $msgNo, FT_PEEK)
            : (string) @imap_fetchbody($stream, $msgNo, $partNumber, FT_PEEK);

        return match ((int) ($structure->encoding ?? 0)) {
            3 => (string) base64_decode($raw, true),
            4 => (string) quoted_printable_decode($raw),
            default => $raw,
        };
    }

    private function extractAttachmentName(object $structure): ?string
    {
        $candidates = [];

        foreach ((array) ($structure->dparameters ?? []) as $parameter) {
            if (isset($parameter->attribute, $parameter->value)) {
                $candidates[strtolower((string) $parameter->attribute)] = $this->decodeMimeHeader((string) $parameter->value);
            }
        }

        foreach ((array) ($structure->parameters ?? []) as $parameter) {
            if (isset($parameter->attribute, $parameter->value)) {
                $candidates[strtolower((string) $parameter->attribute)] = $this->decodeMimeHeader((string) $parameter->value);
            }
        }

        return $candidates['filename'] ?? $candidates['name'] ?? null;
    }

    private function hasAttachmentDisposition(object $structure): bool
    {
        $disposition = strtolower((string) ($structure->disposition ?? ''));
        return in_array($disposition, ['attachment', 'inline'], true);
    }

    private function resolveMimeType(int $type, string $subtype): string
    {
        $base = match ($type) {
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            default => 'application',
        };

        return $subtype !== '' ? $base . '/' . $subtype : 'application/octet-stream';
    }

    private function replaceAttachments(MailboxMessage $message, array $attachments): int
    {
        $disk = (string) config('mailbox.attachment_disk', 'local');

        foreach ($message->attachments as $existing) {
            if ($existing->file_path && Storage::disk($existing->disk ?: $disk)->exists($existing->file_path)) {
                Storage::disk($existing->disk ?: $disk)->delete($existing->file_path);
            }
        }

        $message->attachments()->delete();

        $created = 0;

        foreach ($attachments as $index => $attachment) {
            $fileName = (string) ($attachment['file_name'] ?? ('attachment-' . ($index + 1)));
            $safeFileName = Str::slug(pathinfo($fileName, PATHINFO_FILENAME)) ?: 'attachment';
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $storedName = $safeFileName . '-' . Str::random(10) . ($extension ? '.' . $extension : '');
            $path = 'mailbox/' . $message->user_id . '/' . $message->id . '/' . $storedName;

            Storage::disk($disk)->put($path, (string) ($attachment['content'] ?? ''));

            $message->attachments()->create([
                'file_name' => $fileName,
                'file_path' => $path,
                'disk' => $disk,
                'mime_type' => $attachment['mime_type'] ?? null,
                'file_extension' => $extension ?: null,
                'file_size' => strlen((string) ($attachment['content'] ?? '')),
                'content_id' => $attachment['content_id'] ?? null,
                'sort_order' => $index,
            ]);

            $created++;
        }

        return $created;
    }

    private function firstAddress(array $addresses): array
    {
        $row = $this->addressList($addresses)[0] ?? ['email' => null, 'name' => null];

        return [
            'email' => $row['email'] ?? null,
            'name' => $row['name'] ?? null,
        ];
    }

    private function addressList(array $addresses): array
    {
        $items = [];

        foreach ($addresses as $address) {
            $mailbox = isset($address->mailbox) ? (string) $address->mailbox : '';
            $host = isset($address->host) ? (string) $address->host : '';

            $email = ($mailbox !== '' && $host !== '') ? $mailbox . '@' . $host : null;
            $name = $this->decodeMimeHeader((string) ($address->personal ?? ''));

            if ($email) {
                $items[] = [
                    'email' => $email,
                    'name' => $name !== '' ? $name : null,
                ];
            }
        }

        return $items;
    }

    private function decodeMimeHeader(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if (function_exists('imap_mime_header_decode')) {
            $decoded = @imap_mime_header_decode($value);

            if (is_array($decoded)) {
                return trim(collect($decoded)->map(fn ($part) => $part->text ?? '')->implode(''));
            }
        }

        return trim($value);
    }

    private function cleanHeaderIdentifier(?string $value): string
    {
        return trim(str_replace(['<', '>'], '', (string) $value));
    }
}