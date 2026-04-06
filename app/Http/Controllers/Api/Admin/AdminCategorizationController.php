<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Bank;
use App\Models\FinanceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminCategorizationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'in:agents,staff,clients'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $tab = (string) ($validated['tab'] ?? 'agents');
        $perPage = (int) ($validated['per_page'] ?? 12);

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

        $totalClients = (clone $clientsBase)->count();
        $totalStaff = (clone $staffBase)->count();
        $totalAgents = Agent::query()->count();

        $agents = collect();
        $staff = collect();
        $clients = collect();
        $tabPagination = null;

        if ($tab === 'agents') {
            $agentsPaginator = Agent::query()
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
                ->paginate($perPage);

            $agents = collect($agentsPaginator->items())
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

            $tabPagination = $this->paginationMeta($agentsPaginator);
        } elseif ($tab === 'staff') {
            $staffPaginator = (clone $staffBase)
                ->withCount([
                    'staffAssignments as active_assignments_count' => fn ($query) => $query->where('is_active', true),
                    'primaryAssignedFinanceRequests as lead_requests_count',
                    'requestComments as comments_count',
                ])
                ->orderBy('name')
                ->paginate($perPage);

            $staff = collect($staffPaginator->items())
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

            $tabPagination = $this->paginationMeta($staffPaginator);
        } else {
            $clientsPaginator = (clone $clientsBase)
                ->withCount([
                    'financeRequests as requests_count',
                    'financeRequests as active_requests_count' => fn ($query) => $query->whereNotIn('status', [
                        FinanceRequestStatus::COMPLETED->value,
                        FinanceRequestStatus::CANCELLED->value,
                        FinanceRequestStatus::REJECTED->value,
                    ]),
                    'financeRequests as needs_action_count' => fn ($query) => $query->whereIn('workflow_stage', [
                        'awaiting_client_signature',
                        'awaiting_client_commercial_registration_upload',
                        'awaiting_client_documents',
                        'awaiting_additional_documents',
                        'client_update_requested',
                        'contract',
                        'document_collection',
                    ]),
                ])
                ->orderBy('name')
                ->paginate($perPage);

            $clients = collect($clientsPaginator->items())
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

            $tabPagination = $this->paginationMeta($clientsPaginator);
        }

        $bankBreakdown = Bank::query()
            ->leftJoin('agents', 'agents.bank_id', '=', 'banks.id')
            ->leftJoin('request_email_agents', 'request_email_agents.agent_id', '=', 'agents.id')
            ->leftJoin('request_emails', 'request_emails.id', '=', 'request_email_agents.request_email_id')
            ->select([
                'banks.id',
                'banks.name',
                'banks.short_name',
                DB::raw('COUNT(DISTINCT agents.id) as agents_count'),
                DB::raw('COUNT(DISTINCT request_emails.id) as emails_count'),
                DB::raw('COUNT(DISTINCT request_emails.finance_request_id) as requests_count'),
            ])
            ->groupBy('banks.id', 'banks.name', 'banks.short_name')
            ->orderByDesc('emails_count')
            ->orderBy('banks.name')
            ->get()
            ->map(fn ($bank) => [
                'id' => (int) $bank->id,
                'name' => $bank->name,
                'short_name' => $bank->short_name,
                'agents_count' => (int) $bank->agents_count,
                'emails_count' => (int) $bank->emails_count,
                'requests_count' => (int) $bank->requests_count,
            ])
            ->values();

        $pendingQueueCount = FinanceRequest::query()
            ->where('status', FinanceRequestStatus::SUBMITTED->value)
            ->whereIn('workflow_stage', [
                FinanceRequestWorkflowStage::QUESTIONNAIRE->value,
                FinanceRequestWorkflowStage::REVIEW->value,
                FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
            ])
            ->count();

        $contractQueueCount = FinanceRequest::query()
            ->where('status', FinanceRequestStatus::ACTIVE->value)
            ->whereIn('workflow_stage', [
                FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
                FinanceRequestWorkflowStage::CONTRACT->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
                FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value,
            ])
            ->count();

        $assignedQueueCount = FinanceRequest::query()
            ->whereHas('assignments', fn ($query) => $query->where('is_active', true))
            ->whereNotIn('status', [
                FinanceRequestStatus::COMPLETED->value,
                FinanceRequestStatus::REJECTED->value,
                FinanceRequestStatus::CANCELLED->value,
            ])
            ->count();

        $agentsWithTraffic = Agent::query()
            ->whereHas('requestEmails')
            ->count();

        $staffWithAssignments = (clone $staffBase)
            ->whereHas('staffAssignments', fn ($query) => $query->where('is_active', true))
            ->count();

        $clientsNeedingAction = (clone $clientsBase)
            ->whereHas('financeRequests', fn ($query) => $query->whereIn('workflow_stage', [
                'awaiting_client_signature',
                'awaiting_client_commercial_registration_upload',
                'awaiting_client_documents',
                'awaiting_additional_documents',
                'client_update_requested',
                'contract',
                'document_collection',
            ]))
            ->count();

        $requestTrend = $this->requestTrend();
        $bankChart = $bankBreakdown->take(6)->values();

        return response()->json([
            'summary' => [
                'total_requests' => FinanceRequest::count(),
                'submitted_requests' => (int) ($requestStatusCounts[FinanceRequestStatus::SUBMITTED->value] ?? 0),
                'active_requests' => (int) ($requestStatusCounts[FinanceRequestStatus::ACTIVE->value] ?? 0),
                'completed_requests' => (int) ($requestStatusCounts[FinanceRequestStatus::COMPLETED->value] ?? 0),
                'total_clients' => $totalClients,
                'total_staff' => $totalStaff,
                'total_agents' => $totalAgents,
                'with_additional_document_requests' => FinanceRequest::query()->whereHas('additionalDocuments')->count(),
                'pending_queue_requests' => $pendingQueueCount,
                'contract_queue_requests' => $contractQueueCount,
                'assigned_queue_requests' => $assignedQueueCount,
            ],
            'signals' => [
                'agents_with_traffic' => $agentsWithTraffic,
                'staff_with_assignments' => $staffWithAssignments,
                'clients_needing_action' => $clientsNeedingAction,
            ],
            'tab' => $tab,
            'status_breakdown' => $requestStatusCounts,
            'stage_breakdown' => $workflowStageCounts,
            'charts' => [
                'request_trend' => $requestTrend,
                'bank_email_breakdown' => [
                    'labels' => $bankChart->pluck('short_name')->map(fn ($value, $index) => $value ?: $bankChart[$index]['name'])->values(),
                    'email_series' => $bankChart->pluck('emails_count')->values(),
                    'request_series' => $bankChart->pluck('requests_count')->values(),
                ],
            ],
            'bank_breakdown' => $bankBreakdown,
            'agents' => $agents,
            'staff' => $staff,
            'clients' => $clients,
            'pagination' => $tabPagination,
        ]);
    }

    private function requestTrend(): array
    {
        $labels = [];
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end = (clone $start)->endOfMonth();

            $labels[] = $start->format('M Y');

            $series[] = FinanceRequest::query()
                ->where(function ($query) use ($start, $end) {
                    $query
                        ->whereBetween('submitted_at', [$start, $end])
                        ->orWhere(function ($inner) use ($start, $end) {
                            $inner->whereNull('submitted_at')
                                ->whereBetween('created_at', [$start, $end]);
                        });
                })
                ->count();
        }

        return [
            'labels' => $labels,
            'series' => $series,
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
