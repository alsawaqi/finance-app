<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Enums\UserAccountType;
use App\Models\User;
use App\Services\MailboxSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mailbox:sync {userId?} {--limit=40} {--folder=}', function (?int $userId, MailboxSyncService $mailboxSyncService) {
    $folder = $this->option('folder') ?: null;
    $limit = (int) $this->option('limit') ?: null;

    $users = User::query()
        ->where(function ($query) {
            $query->where('account_type', UserAccountType::STAFF->value)
                ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'staff'));
        })
        ->when($userId, fn ($query) => $query->where('id', $userId))
        ->orderBy('name')
        ->get()
        ->filter(fn (User $user) => $user->hasVerifiedMailboxSettings())
        ->values();

    if ($users->isEmpty()) {
        $this->warn('No verified staff mailboxes were found to sync.');
        return self::FAILURE;
    }

    foreach ($users as $user) {
        try {
            $result = $mailboxSyncService->syncInbox($user, $folder, $limit);
            $this->info(sprintf('[%s] Synced %s: created=%d updated=%d attachments=%d', $user->id, $user->email, $result['created'] ?? 0, $result['updated'] ?? 0, $result['attachments_created'] ?? 0));
        } catch (\Throwable $exception) {
            $this->error(sprintf('[%s] %s failed: %s', $user->id, $user->email, $exception->getMessage()));
        }
    }

    return self::SUCCESS;
})->purpose('Synchronize verified staff mailboxes through IMAP');
