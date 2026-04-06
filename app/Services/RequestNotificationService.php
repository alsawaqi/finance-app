<?php

namespace App\Services;

use App\Enums\RequestCommentVisibility;
use App\Enums\UserAccountType;
use App\Models\FinanceRequest;
use App\Models\RequestTimeline;
use App\Models\User;
use App\Notifications\AppSystemNotification;
use App\Services\Twilio\ClientStageWhatsAppNotifier;
use Illuminate\Support\Collection;
use Throwable;

class RequestNotificationService
{
    /**
     * @var string[]
     */
    private array $clientAllowedEvents = [
        'contract.admin_signed',
        'contract.admin_uploaded_and_auto_completed',
        'request.comment_added',
        'request.client_update_requested',
        'request.required_document_change_requested',
        'request.additional_document_requested',
        'request.final_approved',
        'request.rejected',
    ];

    public function __construct(
        private readonly ClientStageWhatsAppNotifier $clientStageWhatsAppNotifier,
    ) {
    }

    public function dispatchFromTimeline(FinanceRequest $financeRequest, RequestTimeline $timeline): void
    {
        $eventType = (string) $timeline->event_type;
        $actorUserId = $timeline->actor_user_id ? (int) $timeline->actor_user_id : null;
        $metadata = is_array($timeline->metadata_json) ? $timeline->metadata_json : [];

        $this->dispatchClientWhatsApp($financeRequest, $timeline);

        $recipients = $this->resolveRecipients($financeRequest, $eventType, $actorUserId, $metadata);

        if ($recipients->isEmpty()) {
            return;
        }

        $basePayload = $this->basePayload($financeRequest, $timeline);

        foreach ($recipients as $recipient) {
            $recipientRole = $this->resolveRecipientRole($recipient);

            $recipient->notify(new AppSystemNotification([
                ...$basePayload,
                'recipient_role' => $recipientRole,
                'target' => $this->targetFor($recipientRole, $financeRequest, $eventType),
            ]));
        }
    }

    private function resolveRecipients(
        FinanceRequest $financeRequest,
        string $eventType,
        ?int $actorUserId,
        array $metadata = [],
    ): Collection {
        $recipientIds = collect();

        $adminIds = User::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('account_type', UserAccountType::ADMIN->value)
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', UserAccountType::ADMIN->value));
            })
            ->pluck('id');

        $recipientIds = $recipientIds->merge($adminIds);

        $assignedStaffIds = $financeRequest->assignments()
            ->where('is_active', true)
            ->pluck('staff_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($financeRequest->primary_staff_id) {
            $assignedStaffIds->push((int) $financeRequest->primary_staff_id);
        }

        if ($assignedStaffIds->isNotEmpty()) {
            $staffIds = User::query()
                ->where('is_active', true)
                ->whereIn('id', $assignedStaffIds->unique()->values())
                ->pluck('id');

            $recipientIds = $recipientIds->merge($staffIds);
        }

        if ($this->shouldNotifyClient($eventType, $metadata) && $financeRequest->user_id) {
            $clientId = User::query()
                ->where('id', (int) $financeRequest->user_id)
                ->where('is_active', true)
                ->value('id');

            if ($clientId) {
                $recipientIds->push((int) $clientId);
            }
        }

        $uniqueIds = $recipientIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($actorUserId) {
            $uniqueIds = $uniqueIds
                ->reject(fn (int $id) => $id === $actorUserId)
                ->values();
        }

        if ($uniqueIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $uniqueIds)
            ->get();
    }

    private function shouldNotifyClient(string $eventType, array $metadata = []): bool
    {
        if ($eventType === 'request.comment_added') {
            return (string) ($metadata['visibility'] ?? '') === RequestCommentVisibility::CLIENT_VISIBLE->value;
        }

        return in_array($eventType, $this->clientAllowedEvents, true);
    }

    private function dispatchClientWhatsApp(FinanceRequest $financeRequest, RequestTimeline $timeline): void
    {
        try {
            $this->clientStageWhatsAppNotifier->notifyFromTimeline($financeRequest, $timeline);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function resolveRecipientRole(User $recipient): string
    {
        if ($recipient->isClient()) {
            return 'client';
        }

        if ($recipient->isAdmin()) {
            return 'admin';
        }

        if ($recipient->isStaff()) {
            return 'staff';
        }

        return 'user';
    }

    private function basePayload(FinanceRequest $financeRequest, RequestTimeline $timeline): array
    {
        $workflowStage = $financeRequest->workflow_stage?->value ?? (string) $financeRequest->workflow_stage;
        $status = $financeRequest->status?->value ?? (string) $financeRequest->status;
        $metadata = is_array($timeline->metadata_json) ? $timeline->metadata_json : [];

        return [
            'event_type' => (string) $timeline->event_type,
            'event_title_en' => (string) ($timeline->event_title_en ?: $timeline->event_title ?: 'Request update'),
            'event_title_ar' => (string) ($timeline->event_title_ar ?: $timeline->event_title ?: 'تحديث على الطلب'),
            'event_description_en' => $timeline->event_description_en ?: $timeline->event_description,
            'event_description_ar' => $timeline->event_description_ar ?: $timeline->event_description,
            'request_id' => (int) $financeRequest->id,
            'reference_number' => $financeRequest->reference_number,
            'approval_reference_number' => $financeRequest->approval_reference_number,
            'company_name' => $financeRequest->company_name ?? data_get($financeRequest->intake_details_json, 'company_name'),
            'workflow_stage' => $workflowStage,
            'status' => $status,
            'actor_user_id' => $timeline->actor_user_id ? (int) $timeline->actor_user_id : null,
            'metadata' => $metadata,
            'created_at' => optional($timeline->created_at)?->toISOString(),
        ];
    }

    private function targetFor(string $recipientRole, FinanceRequest $financeRequest, string $eventType): array
    {
        $requestId = (string) $financeRequest->id;

        if ($recipientRole === 'client') {
            if ($eventType === 'contract.admin_signed') {
                return [
                    'route_name' => 'client-request-sign',
                    'params' => ['id' => $requestId],
                    'path' => "/dashboard/requests/{$requestId}/sign",
                ];
            }

            if (in_array($eventType, [
                'request.required_document_change_requested',
                'request.additional_document_requested',
            ], true)) {
                return [
                    'route_name' => 'client-request-documents',
                    'params' => ['id' => $requestId],
                    'path' => "/dashboard/requests/{$requestId}/documents",
                ];
            }

            return [
                'route_name' => 'client-request-details',
                'params' => ['id' => $requestId],
                'path' => "/dashboard/requests/{$requestId}",
            ];
        }

        if ($recipientRole === 'staff') {
            return [
                'route_name' => 'staff-request-details',
                'params' => ['id' => $requestId],
                'path' => "/admin/assigned-requests/{$requestId}",
            ];
        }

        return [
            'route_name' => 'admin-request-details',
            'params' => ['id' => $requestId],
            'path' => "/admin/requests/{$requestId}",
        ];
    }
}
