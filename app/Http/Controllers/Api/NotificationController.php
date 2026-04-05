<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            'unread_only' => ['nullable', 'boolean'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $unreadOnly = (bool) ($validated['unread_only'] ?? false);

        $paginator = $request->user()
            ->notifications()
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($perPage);

        $notifications = collect($paginator->items())
            ->map(fn (DatabaseNotification $notification) => $this->serializeNotification($notification))
            ->values();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $request->user()
            ->notifications()
            ->where('id', $notificationId)
            ->first();

        abort_unless($notification instanceof DatabaseNotification, 404);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $freshNotification = $notification->fresh();
        if ($freshNotification instanceof DatabaseNotification) {
            $notification = $freshNotification;
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => $this->serializeNotification($notification),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'updated_count' => (int) $updated,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    private function serializeNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'event_type' => data_get($data, 'event_type'),
            'title_en' => data_get($data, 'event_title_en'),
            'title_ar' => data_get($data, 'event_title_ar'),
            'description_en' => data_get($data, 'event_description_en'),
            'description_ar' => data_get($data, 'event_description_ar'),
            'reference_number' => data_get($data, 'reference_number'),
            'approval_reference_number' => data_get($data, 'approval_reference_number'),
            'company_name' => data_get($data, 'company_name'),
            'workflow_stage' => data_get($data, 'workflow_stage'),
            'status' => data_get($data, 'status'),
            'request_id' => data_get($data, 'request_id'),
            'recipient_role' => data_get($data, 'recipient_role'),
            'target' => data_get($data, 'target'),
            'metadata' => data_get($data, 'metadata', []),
            'created_at' => optional($notification->created_at)?->toISOString(),
            'read_at' => optional($notification->read_at)?->toISOString(),
            'is_read' => $notification->read_at !== null,
        ];
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
