<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\RequestCommentVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequestCommentRequest;
use App\Models\Agent;
use App\Models\FinanceRequest;
use App\Models\RequestComment;
use App\Models\RequestTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffRequestWorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('view assigned requests')), 403);

        $query = FinanceRequest::query()
            ->with([
                'client:id,name,email',
                'currentContract:id,finance_request_id,version_no,status,client_signed_at',
                'assignments' => fn ($assignmentQuery) => $assignmentQuery
                    ->where('is_active', true)
                    ->with('staff:id,name,email')
                    ->orderByDesc('is_primary')
                    ->orderBy('assigned_at'),
            ])
            ->withCount('comments')
            ->whereIn('workflow_stage', [
                FinanceRequestWorkflowStage::ASSIGNED_TO_STAFF->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
                FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
            ]);

        if (! $user->hasRole('admin')) {
            $query->where(function ($innerQuery) use ($user) {
                $innerQuery->where('primary_staff_id', $user->id)
                    ->orWhereHas('assignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery
                            ->where('staff_id', $user->id)
                            ->where('is_active', true);
                    });
            });
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($searchQuery) use ($term) {
                $searchQuery->where('reference_number', 'like', "%{$term}%")
                    ->orWhere('approval_reference_number', 'like', "%{$term}%")
                    ->orWhereHas('client', function ($clientQuery) use ($term) {
                        $clientQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('workflow_stage')) {
            $query->where('workflow_stage', (string) $request->input('workflow_stage'));
        }

        $requests = $query
            ->orderByRaw("CASE WHEN workflow_stage = ? THEN 0 WHEN workflow_stage = ? THEN 1 ELSE 2 END", [
                FinanceRequestWorkflowStage::ASSIGNED_TO_STAFF->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
            ])
            ->orderByDesc('latest_activity_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'requests' => $requests,
        ]);
    }

    public function show(Request $request, FinanceRequest $financeRequest): JsonResponse
    {
        $this->ensureVisibleToUser($request->user(), $financeRequest);

        $financeRequest->load([
            'client:id,name,email,phone',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments.uploader:id,name',
            'currentContract',
            'timeline.actor:id,name',
            'assignments' => fn ($query) => $query->where('is_active', true)->with(['staff:id,name,email', 'assignedBy:id,name,email'])->orderByDesc('is_primary')->orderBy('assigned_at'),
            'comments' => fn ($query) => $query->with('user:id,name,email')->latest(),
        ]);

        return response()->json([
            'request' => $financeRequest,
        ]);
    }

    public function storeComment(StoreStaffRequestCommentRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisibleToUser($user, $financeRequest);
        abort_unless($user && ($user->hasRole('admin') || $user->can('add internal comments')), 403);

        $comment = DB::transaction(function () use ($request, $financeRequest, $user) {
            $comment = RequestComment::create([
                'finance_request_id' => $financeRequest->id,
                'user_id' => $user?->id,
                'parent_id' => null,
                'comment_text' => (string) $request->input('comment_text'),
                'visibility' => RequestCommentVisibility::from((string) $request->input('visibility')),
            ]);

            if (in_array($financeRequest->workflow_stage?->value ?? $financeRequest->workflow_stage, [
                FinanceRequestWorkflowStage::ASSIGNED_TO_STAFF->value,
                FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
            ], true)) {
                $financeRequest->workflow_stage = FinanceRequestWorkflowStage::PROCESSING;
            }

            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimeline::create([
                'finance_request_id' => $financeRequest->id,
                'actor_user_id' => $user?->id,
                'event_type' => 'request.comment_added',
                'event_title' => 'Internal follow-up comment added',
                'event_description' => str($comment->comment_text)->limit(240)->toString(),
                'metadata_json' => [
                    'comment_id' => $comment->id,
                    'visibility' => $comment->visibility?->value,
                ],
                'created_at' => now(),
            ]);

            return $comment->load('user:id,name,email');
        });

        return response()->json([
            'message' => 'Comment added successfully.',
            'comment' => $comment,
            'request' => $financeRequest->fresh([
                'timeline.actor:id,name',
                'comments' => fn ($query) => $query->with('user:id,name,email')->latest(),
                'assignments' => fn ($query) => $query->where('is_active', true)->with('staff:id,name,email')->orderByDesc('is_primary')->orderBy('assigned_at'),
                'currentContract',
            ]),
        ], 201);
    }

    public function agents(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('view assigned requests')), 403);

        $agents = Agent::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'company_name', 'agent_type']);

        return response()->json([
            'agents' => $agents,
        ]);
    }

    private function ensureVisibleToUser($user, FinanceRequest $financeRequest): void
    {
        abort_unless($user && ($user->hasRole('admin') || $user->can('view assigned requests')), 403);

        if ($user->hasRole('admin')) {
            return;
        }

        $isAssigned = (int) $financeRequest->primary_staff_id === (int) $user->id
            || $financeRequest->assignments()
                ->where('staff_id', $user->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($isAssigned, 403, 'You are not assigned to this request.');
    }
}
