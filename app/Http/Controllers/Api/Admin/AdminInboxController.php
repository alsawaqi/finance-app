<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Models\MailboxMessage;
use App\Models\MailboxMessageAttachment;
use App\Models\User;
use App\Services\MailboxSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminInboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->integer('user_id');
        $onlyUnread = $request->boolean('only_unread');
        $search = trim((string) $request->string('search'));

        $messages = MailboxMessage::query()
            ->with(['user:id,name,email', 'attachments:id,mailbox_message_id'])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($onlyUnread, fn ($query) => $query->where('is_read', false))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('subject', 'like', "%{$search}%")
                        ->orWhere('from_email', 'like', "%{$search}%")
                        ->orWhere('from_name', 'like', "%{$search}%")
                        ->orWhere('body_text', 'like', "%{$search}%");
                });
            })
            ->latest('received_at')
            ->latest('id')
            ->paginate(25);

        $staffUsers = User::query()
            ->where('account_type', UserAccountType::STAFF->value)
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'staff'))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values();

        return response()->json([
            'messages' => collect($messages->items())->map(fn (MailboxMessage $message) => $this->serializeListRow($message))->values(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
            'staff_users' => $staffUsers,
        ]);
    }

    public function show(MailboxMessage $mailboxMessage): JsonResponse
    {
        $mailboxMessage->load([
            'user:id,name,email',
            'attachments',
        ]);

        if (! $mailboxMessage->is_read) {
            $mailboxMessage->forceFill(['is_read' => true])->save();
        }

        return response()->json([
            'message' => $this->serializeDetail($mailboxMessage->fresh(['user:id,name,email', 'attachments'])),
        ]);
    }

    public function sync(Request $request, MailboxSyncService $mailboxSyncService): JsonResponse
    {
        $userId = $request->integer('user_id');
        $folder = $request->string('folder')->toString() ?: null;
        $limit = $request->integer('limit') ?: null;

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

        $results = [];

        foreach ($users as $user) {
            $results[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'result' => $mailboxSyncService->syncInbox($user, $folder, $limit),
            ];
        }

        return response()->json([
            'message' => 'Inbox synchronization completed.',
            'results' => $results,
        ]);
    }

    public function downloadAttachment(MailboxMessageAttachment $attachment): StreamedResponse
    {
        $message = $attachment->mailboxMessage;
        abort_unless($message, 404);
        abort_unless(Storage::disk($attachment->disk ?: 'local')->exists($attachment->file_path), 404);

        return Storage::disk($attachment->disk ?: 'local')->download(
            $attachment->file_path,
            $attachment->file_name ?: basename($attachment->file_path)
        );
    }

    private function serializeListRow(MailboxMessage $message): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'user_email' => $message->user?->email,
            'folder_name' => $message->folder_name,
            'subject' => $message->subject,
            'from_email' => $message->from_email,
            'from_name' => $message->from_name,
            'received_at' => optional($message->received_at)?->toISOString(),
            'is_read' => (bool) $message->is_read,
            'has_attachments' => (bool) $message->has_attachments,
            'attachment_count' => $message->attachments->count(),
            'preview' => $message->preview(),
        ];
    }

    private function serializeDetail(MailboxMessage $message): array
    {
        return [
            ...$this->serializeListRow($message),
            'to_emails' => $message->to_emails_json ?? [],
            'cc_emails' => $message->cc_emails_json ?? [],
            'message_id' => $message->message_id,
            'in_reply_to' => $message->in_reply_to,
            'references_header' => $message->references_header,
            'body_text' => $message->body_text,
            'body_html' => $message->body_html,
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'file_extension' => $attachment->file_extension,
                'file_size' => $attachment->file_size,
                'download_url' => "/api/admin/inbox/attachments/{$attachment->id}/download",
            ])->values(),
        ];
    }
}