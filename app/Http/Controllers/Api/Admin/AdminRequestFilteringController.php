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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AdminRequestFilteringController extends Controller
{
    public function requests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stage' => [
                'nullable',
                'string',
                Rule::in(array_map(fn (FinanceRequestWorkflowStage $case) => $case->value, FinanceRequestWorkflowStage::cases())),
            ],
            'status' => ['nullable', 'string'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $staffId = isset($validated['staff_id']) ? (int) $validated['staff_id'] : null;
        $bankId = isset($validated['bank_id']) ? (int) $validated['bank_id'] : null;
        $agentId = isset($validated['agent_id']) ? (int) $validated['agent_id'] : null;
        $stage = $validated['stage'] ?? null;
        $status = $validated['status'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        if ($staffId && ($bankId || $agentId)) {
            return response()->json([
                'message' => 'Use either the staff filter or the bank/agent filter, not both together.',
            ], 422);
        }

        $baseQuery = FinanceRequest::query()
            ->with([
                'client:id,name,email,phone',
                'primaryStaff:id,name,email',
                'assignments' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('staff:id,name,email')
                    ->orderByDesc('is_primary')
                    ->orderBy('assigned_at'),
                'emails' => fn ($query) => $query
                    ->select('id', 'finance_request_id', 'sent_at', 'created_at')
                    ->with(['agents' => fn ($agentQuery) => $agentQuery
                        ->select('agents.id', 'agents.name', 'agents.email', 'agents.bank_id')
                        ->with('bank:id,name,short_name'),
                    ]),
            ])
            ->withCount('emails')
            ->when($stage, fn (Builder $query) => $query->where('workflow_stage', $stage))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($staffId, function (Builder $query) use ($staffId) {
                $query->where(function (Builder $staffQuery) use ($staffId) {
                    $staffQuery
                        ->where('primary_staff_id', $staffId)
                        ->orWhereHas('assignments', function (Builder $assignmentQuery) use ($staffId) {
                            $assignmentQuery
                                ->where('staff_id', $staffId)
                                ->where('is_active', true);
                        });
                });
            })
            ->when($bankId || $agentId, function (Builder $query) use ($bankId, $agentId) {
                $query->whereHas('emails.agents', function (Builder $agentQuery) use ($bankId, $agentId) {
                    if ($bankId) {
                        $agentQuery->where('agents.bank_id', $bankId);
                    }

                    if ($agentId) {
                        $agentQuery->where('agents.id', $agentId);
                    }
                });
            })
            ->orderByDesc('latest_activity_at')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $requestCollection = (clone $baseQuery)->get();
        $requestsPaginator = (clone $baseQuery)->paginate($perPage);

        $bankBreakdown = $this->buildBankBreakdown($requestCollection);
        $agentBreakdown = $this->buildAgentBreakdown($requestCollection);

        $requests = collect($requestsPaginator->items())
            ->map(fn (FinanceRequest $financeRequest) => $this->transformRequest($financeRequest))
            ->values();

        return response()->json([
            'filters' => [
                'stages' => collect(FinanceRequestWorkflowStage::cases())
                    ->map(fn (FinanceRequestWorkflowStage $case) => ['value' => $case->value, 'label' => str_replace('_', ' ', $case->value)])
                    ->values(),
                'statuses' => collect(FinanceRequestStatus::cases())
                    ->map(fn (FinanceRequestStatus $case) => ['value' => $case->value, 'label' => str_replace('_', ' ', $case->value)])
                    ->values(),
                'staff' => $this->staffOptions(),
                'banks' => $this->bankOptions(),
                'agents' => $this->agentOptions(),
            ],
            'summary' => [
                'total_requests' => $requests->count(),
                'unique_clients' => $requests->pluck('client.id')->filter()->unique()->count(),
                'unique_staff' => $requests->flatMap(fn (array $item) => $item['active_staff'] ?? [])->pluck('id')->filter()->unique()->count(),
                'unique_agents' => $requests->flatMap(fn (array $item) => $item['agents'] ?? [])->pluck('id')->filter()->unique()->count(),
                'total_emails' => $requestCollection->sum('emails_count'),
            ],
            'bank_breakdown' => $bankBreakdown,
            'agent_breakdown' => $agentBreakdown,
            'requests' => $requests,
            'pagination' => $this->paginationMeta($requestsPaginator),
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $state = (string) ($validated['state'] ?? 'active');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $clientsQuery = $this->clientsBaseQuery()
            ->withCount([
                'financeRequests as requests_count',
                'financeRequests as active_requests_count' => fn (Builder $query) => $query->whereNotIn('status', [
                    FinanceRequestStatus::COMPLETED->value,
                    FinanceRequestStatus::CANCELLED->value,
                    FinanceRequestStatus::REJECTED->value,
                ]),
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });

        if ($state === 'active') {
            $clientsQuery->where('is_active', true);
        } elseif ($state === 'inactive') {
            $clientsQuery->where('is_active', false);
        }

        $clientsWithRequests = (clone $clientsQuery)
            ->whereHas('financeRequests')
            ->count();

        $clientsWithActiveRequests = (clone $clientsQuery)
            ->whereHas('financeRequests', fn (Builder $query) => $query->whereNotIn('status', [
                FinanceRequestStatus::COMPLETED->value,
                FinanceRequestStatus::CANCELLED->value,
                FinanceRequestStatus::REJECTED->value,
            ]))
            ->count();

        $clientsPaginator = (clone $clientsQuery)
            ->orderByDesc('requests_count')
            ->orderBy('name')
            ->paginate($perPage);

        $clients = collect($clientsPaginator->items())
            ->map(fn (User $client) => $this->serializeClient($client))
            ->values();

        return response()->json([
            'summary' => [
                'total_clients' => $clientsPaginator->total(),
                'clients_with_requests' => $clientsWithRequests,
                'clients_with_active_requests' => $clientsWithActiveRequests,
            ],
            'clients' => $clients,
            'pagination' => $this->paginationMeta($clientsPaginator),
        ]);
    }

    public function clientRequests(Request $request, User $client): JsonResponse
    {
        abort_unless($this->isClient($client), 404, 'Client not found.');

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);

        $requestsPaginator = $client->financeRequests()
            ->with([
                'primaryStaff:id,name,email',
                'assignments' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('staff:id,name,email')
                    ->orderByDesc('is_primary')
                    ->orderBy('assigned_at'),
            ])
            ->withCount('emails')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $requests = collect($requestsPaginator->items())
            ->map(function (FinanceRequest $financeRequest) {
                return [
                    'id' => $financeRequest->id,
                    'reference_number' => $financeRequest->reference_number,
                    'approval_reference_number' => $financeRequest->approval_reference_number,
                    'company_name' => $financeRequest->company_name ?? data_get($financeRequest->intake_details_json, 'company_name'),
                    'country_code' => $financeRequest->country_code,
                    'intake_details_json' => $financeRequest->intake_details_json,
                    'status' => $financeRequest->status?->value ?? $financeRequest->status,
                    'workflow_stage' => $financeRequest->workflow_stage?->value ?? $financeRequest->workflow_stage,
                    'submitted_at' => optional($financeRequest->submitted_at)?->toISOString(),
                    'latest_activity_at' => optional($financeRequest->latest_activity_at)?->toISOString(),
                    'emails_count' => (int) $financeRequest->emails_count,
                    'primary_staff' => $financeRequest->primaryStaff ? [
                        'id' => $financeRequest->primaryStaff->id,
                        'name' => $financeRequest->primaryStaff->name,
                        'email' => $financeRequest->primaryStaff->email,
                    ] : null,
                    'active_staff' => $financeRequest->assignments
                        ->map(fn ($assignment) => $assignment->staff ? [
                            'id' => $assignment->staff->id,
                            'name' => $assignment->staff->name,
                            'email' => $assignment->staff->email,
                            'is_primary' => (bool) $assignment->is_primary,
                        ] : null)
                        ->filter()
                        ->values(),
                ];
            })
            ->values();

        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
            ],
            'requests' => $requests,
            'pagination' => $this->paginationMeta($requestsPaginator),
        ]);
    }

    public function toggleClientActive(User $client): JsonResponse
    {
        abort_unless($this->isClient($client), 404, 'Client not found.');

        $client->update([
            'is_active' => ! $client->is_active,
        ]);

        $freshClient = $client->fresh();
        abort_unless($freshClient instanceof User, 404, 'Client not found.');

        return response()->json([
            'message' => $freshClient->is_active
                ? 'Client account activated successfully.'
                : 'Client account deactivated successfully.',
            'client' => $this->serializeClient($freshClient),
        ]);
    }

    private function transformRequest(FinanceRequest $financeRequest): array
    {
        $agents = $financeRequest->emails
            ->flatMap(fn ($email) => $email->agents)
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'bank_id' => $agent->bank_id,
                'bank_name' => $agent->bank?->name,
                'bank_short_name' => $agent->bank?->short_name,
            ])
            ->values();

        $activeStaff = $financeRequest->assignments
            ->map(fn ($assignment) => $assignment->staff ? [
                'id' => $assignment->staff->id,
                'name' => $assignment->staff->name,
                'email' => $assignment->staff->email,
                'is_primary' => (bool) $assignment->is_primary,
            ] : null)
            ->filter()
            ->values();

        $latestEmailAt = $financeRequest->emails
            ->map(fn ($email) => $email->sent_at ?? $email->created_at)
            ->filter()
            ->max();

        return [
            'id' => $financeRequest->id,
            'reference_number' => $financeRequest->reference_number,
            'approval_reference_number' => $financeRequest->approval_reference_number,
            'company_name' => $financeRequest->company_name ?? data_get($financeRequest->intake_details_json, 'company_name'),
            'country_code' => $financeRequest->country_code,
            'intake_details_json' => $financeRequest->intake_details_json,
            'status' => $financeRequest->status?->value ?? $financeRequest->status,
            'workflow_stage' => $financeRequest->workflow_stage?->value ?? $financeRequest->workflow_stage,
            'submitted_at' => optional($financeRequest->submitted_at)?->toISOString(),
            'latest_activity_at' => optional($financeRequest->latest_activity_at)?->toISOString(),
            'latest_email_at' => $this->iso($latestEmailAt),
            'emails_count' => (int) $financeRequest->emails_count,
            'client' => $financeRequest->client ? [
                'id' => $financeRequest->client->id,
                'name' => $financeRequest->client->name,
                'email' => $financeRequest->client->email,
                'phone' => $financeRequest->client->phone,
            ] : null,
            'primary_staff' => $financeRequest->primaryStaff ? [
                'id' => $financeRequest->primaryStaff->id,
                'name' => $financeRequest->primaryStaff->name,
                'email' => $financeRequest->primaryStaff->email,
            ] : null,
            'active_staff' => $activeStaff,
            'agents' => $agents,
        ];
    }

    private function buildBankBreakdown(Collection $requests): Collection
    {
        return $requests
            ->flatMap(function (FinanceRequest $financeRequest) {
                return $financeRequest->emails->flatMap(function ($email) use ($financeRequest) {
                    return $email->agents
                        ->filter(fn ($agent) => !blank($agent->bank_id))
                        ->map(fn ($agent) => [
                            'bank_id' => (int) $agent->bank_id,
                            'bank_name' => $agent->bank?->name,
                            'bank_short_name' => $agent->bank?->short_name,
                            'agent_id' => (int) $agent->id,
                            'email_id' => (int) $email->id,
                            'request_id' => (int) $financeRequest->id,
                        ]);
                });
            })
            ->groupBy('bank_id')
            ->map(function (Collection $items, $bankId) {
                $first = $items->first();

                return [
                    'id' => (int) $bankId,
                    'name' => $first['bank_name'] ?: 'Unknown bank',
                    'short_name' => $first['bank_short_name'],
                    'agents_count' => $items->pluck('agent_id')->unique()->count(),
                    'emails_count' => $items->pluck('email_id')->unique()->count(),
                    'requests_count' => $items->pluck('request_id')->unique()->count(),
                ];
            })
            ->sortByDesc('emails_count')
            ->values();
    }

    private function buildAgentBreakdown(Collection $requests): Collection
    {
        return $requests
            ->flatMap(function (FinanceRequest $financeRequest) {
                return $financeRequest->emails->flatMap(function ($email) use ($financeRequest) {
                    return $email->agents->map(fn ($agent) => [
                        'agent_id' => (int) $agent->id,
                        'name' => $agent->name,
                        'email' => $agent->email,
                        'bank_id' => $agent->bank_id ? (int) $agent->bank_id : null,
                        'bank_name' => $agent->bank?->name,
                        'bank_short_name' => $agent->bank?->short_name,
                        'email_id' => (int) $email->id,
                        'request_id' => (int) $financeRequest->id,
                    ]);
                });
            })
            ->groupBy('agent_id')
            ->map(function (Collection $items, $agentId) {
                $first = $items->first();

                return [
                    'id' => (int) $agentId,
                    'name' => $first['name'],
                    'email' => $first['email'],
                    'bank_id' => $first['bank_id'],
                    'bank_name' => $first['bank_name'],
                    'bank_short_name' => $first['bank_short_name'],
                    'emails_count' => $items->pluck('email_id')->unique()->count(),
                    'requests_count' => $items->pluck('request_id')->unique()->count(),
                ];
            })
            ->sortByDesc('emails_count')
            ->values();
    }

    private function staffOptions()
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->where('account_type', UserAccountType::STAFF->value)
                    ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'staff'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values();
    }

    private function bankOptions()
    {
        return Bank::query()
            ->withCount('agents')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'code'])
            ->map(fn (Bank $bank) => [
                'id' => $bank->id,
                'name' => $bank->name,
                'short_name' => $bank->short_name,
                'code' => $bank->code,
                'agents_count' => (int) $bank->agents_count,
            ])
            ->values();
    }

    private function agentOptions()
    {
        return Agent::query()
            ->with('bank:id,name,short_name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'bank_id', 'is_active'])
            ->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'bank_id' => $agent->bank_id,
                'bank_name' => $agent->bank?->name,
                'bank_short_name' => $agent->bank?->short_name,
                'is_active' => (bool) $agent->is_active,
            ])
            ->values();
    }

    private function clientsBaseQuery(): Builder
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->where('account_type', UserAccountType::CLIENT->value)
                    ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'client'));
            });
    }

    private function isClient(User $user): bool
    {
        return $user->account_type === UserAccountType::CLIENT || $user->hasRole('client');
    }

    private function serializeClient(User $client): array
    {
        $lastRequestAt = $client->financeRequests()->max('submitted_at');

        return [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'is_active' => (bool) $client->is_active,
            'requests_count' => (int) ($client->requests_count ?? 0),
            'active_requests_count' => (int) ($client->active_requests_count ?? 0),
            'last_request_at' => $this->iso($lastRequestAt),
            'last_login_at' => optional($client->last_login_at)?->toISOString(),
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

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toISOString();
        }

        return Carbon::parse($value)->toISOString();
    }
}
