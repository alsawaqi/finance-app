<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\FinanceRequestStatus;
use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\FinanceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminCategorizationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $requestStatusCounts = FinanceRequest::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $workflowStageCounts = FinanceRequest::query()
            ->select('workflow_stage', DB::raw('count(*) as total'))
            ->groupBy('workflow_stage')
            ->pluck('total', 'workflow_stage');

        $clientsBase = User::query()
            ->where(function ($query) {
                $query->where('account_type', UserAccountType::CLIENT->value)
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'client'));
            });

        $staffBase = User::query()
            ->where(function ($query) {
                $query->where('account_type', UserAccountType::STAFF->value)
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'staff'));
            });

        $agents = Agent::query()
            ->leftJoin('banks', 'banks.id', '=', 'agents.bank_id')
            ->leftJoin('request_email_agents', 'request_email_agents.agent_id', '=', 'agents.id')
            ->leftJoin('request_emails', 'request_emails.id', '=', 'request_email_agents.request_email_id')
            ->select([
                'agents.id',
                'agents.name',
                'agents.email',
                'agents.phone',
                'agents.is_active',
                'agents.bank_id',
                'banks.name as bank_name',
                'banks.short_name as bank_short_name',
                DB::raw('COUNT(DISTINCT request_email_agents.request_email_id) as emails_count'),
                DB::raw('COUNT(DISTINCT request_emails.finance_request_id) as requests_count'),
                DB::raw('MAX(COALESCE(request_emails.sent_at, request_emails.created_at)) as last_contact_at'),
            ])
            ->groupBy('agents.id', 'agents.name', 'agents.email', 'agents.phone', 'agents.is_active', 'agents.bank_id', 'banks.name', 'banks.short_name')
            ->orderByDesc('emails_count')
            ->orderBy('agents.name')
            ->get()
            ->map(fn ($agent) => [
                'id' => (int) $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'is_active' => (bool) $agent->is_active,
                'bank_id' => $agent->bank_id ? (int) $agent->bank_id : null,
                'bank_name' => $agent->bank_name,
                'bank_short_name' => $agent->bank_short_name,
                'emails_count' => (int) $agent->emails_count,
                'requests_count' => (int) $agent->requests_count,
                'last_contact_at' => $agent->last_contact_at,
            ])
            ->values();

        $staff = $staffBase
            ->withCount([
                'staffAssignments as active_assignments_count' => fn ($query) => $query->where('is_active', true),
                'primaryAssignedFinanceRequests as lead_requests_count',
                'requestComments as comments_count',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $lastAssignedAt = $user->staffAssignments()
                    ->whereNotNull('assigned_at')
                    ->max('assigned_at');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => (bool) $user->is_active,
                    'active_assignments_count' => (int) $user->active_assignments_count,
                    'lead_requests_count' => (int) $user->lead_requests_count,
                    'comments_count' => (int) $user->comments_count,
                    'permission_names' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                    'last_assigned_at' => $lastAssignedAt ? Carbon::parse($lastAssignedAt)->toISOString() : null,
                    'last_login_at' => optional($user->last_login_at)?->toISOString(),
                ];
            })
            ->values();

        $clients = $clientsBase
            ->withCount([
                'financeRequests as requests_count',
                'financeRequests as active_requests_count' => fn ($query) => $query->whereNotIn('status', [
                    FinanceRequestStatus::COMPLETED->value,
                    FinanceRequestStatus::CANCELLED->value,
                    FinanceRequestStatus::REJECTED->value,
                ]),
                'financeRequests as needs_action_count' => fn ($query) => $query->whereIn('workflow_stage', [
                    'contract',
                    'document_collection',
                    'awaiting_additional_documents',
                ]),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $lastRequestAt = $user->financeRequests()->max('submitted_at');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => (bool) $user->is_active,
                    'requests_count' => (int) $user->requests_count,
                    'active_requests_count' => (int) $user->active_requests_count,
                    'needs_action_count' => (int) $user->needs_action_count,
                    'last_request_at' => $lastRequestAt ? Carbon::parse($lastRequestAt)->toISOString() : null,
                    'last_login_at' => optional($user->last_login_at)?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'summary' => [
                'total_requests' => FinanceRequest::count(),
                'submitted_requests' => (int) ($requestStatusCounts[FinanceRequestStatus::SUBMITTED->value] ?? 0),
                'active_requests' => (int) ($requestStatusCounts[FinanceRequestStatus::ACTIVE->value] ?? 0),
                'completed_requests' => (int) ($requestStatusCounts[FinanceRequestStatus::COMPLETED->value] ?? 0),
                'total_clients' => $clients->count(),
                'total_staff' => $staff->count(),
                'total_agents' => $agents->count(),
                'with_additional_document_requests' => FinanceRequest::query()->whereHas('additionalDocuments')->count(),
            ],
            'status_breakdown' => $requestStatusCounts,
            'stage_breakdown' => $workflowStageCounts,
            'agents' => $agents,
            'staff' => $staff,
            'clients' => $clients,
        ]);
    }
}
