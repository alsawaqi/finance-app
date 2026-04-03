<?php

return [
    'imap_host' => env('MAILBOX_IMAP_HOST', 'imap.hostinger.com'),
    'imap_port' => (int) env('MAILBOX_IMAP_PORT', 993),
    'imap_encryption' => env('MAILBOX_IMAP_ENCRYPTION', 'ssl'),
    'imap_validate_cert' => filter_var(env('MAILBOX_IMAP_VALIDATE_CERT', false), FILTER_VALIDATE_BOOL),
    'imap_folder' => env('MAILBOX_IMAP_FOLDER', 'INBOX'),
    'imap_sync_limit' => (int) env('MAILBOX_IMAP_SYNC_LIMIT', 40),
    'attachment_disk' => env('MAILBOX_ATTACHMENT_DISK', 'local'),
];