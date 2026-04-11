<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\MailboxMessage;
use App\Models\MailboxMessageAttachment;
use App\Services\MailboxSyncService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffInboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $onlyUnread = $request->boolean('only_unread');
        $search = trim((string) $request->string('search'));

        $messages = MailboxMessage::query()
            ->where('user_id', $user->id)
            ->with('attachments:id,mailbox_message_id')
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

        return response()->json([
            'messages' => collect($messages->items())->map(fn (MailboxMessage $message) => $this->serializeListRow($message))->values(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function show(Request $request, MailboxMessage $mailboxMessage): JsonResponse
    {
        abort_unless((int) $mailboxMessage->user_id === (int) $request->user()->id, 404);

        $mailboxMessage->load('attachments');

        if (! $mailboxMessage->is_read) {
            $mailboxMessage->forceFill(['is_read' => true])->save();
        }

        return response()->json([
            'message' => $this->serializeDetail($mailboxMessage->fresh('attachments')),
        ]);
    }

    public function sync(Request $request, MailboxSyncService $mailboxSyncService): JsonResponse
    {
        $user = $request->user();
        $folder = $request->string('folder')->toString() ?: null;
        $limit = $request->integer('limit') ?: null;

        $result = $mailboxSyncService->syncInbox($user, $folder, $limit);

        return response()->json([
            'message' => 'Inbox synchronization completed.',
            'result' => $result,
        ]);
    }

    public function downloadAttachment(Request $request, MailboxMessageAttachment $attachment): StreamedResponse
    {
        $message = $attachment->mailboxMessage;
        abort_unless($message && (int) $message->user_id === (int) $request->user()->id, 404);
        $disk = Storage::disk($attachment->disk ?: 'local');
        assert($disk instanceof FilesystemAdapter);

        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->download(
            $attachment->file_path,
            $attachment->file_name ?: basename($attachment->file_path)
        );
    }

    private function serializeListRow(MailboxMessage $message): array
    {
        return [
            'id' => $message->id,
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
                'download_url' => "/api/staff/inbox/attachments/{$attachment->id}/download",
            ])->values(),
        ];
    }
}