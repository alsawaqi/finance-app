<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\FinanceRequestStatus;
use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Bank;
use App\Models\FinanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminRequestFilteringController extends Controller
{
    public function requests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
        ]);

        $staffId = isset($validated['staff_id']) ? (int) $validated['staff_id'] : null;
        $bankId = isset($validated['bank_id']) ? (int) $validated['bank_id'] : null;
        $agentId = isset($validated['agent_id']) ? (int) $validated['agent_id'] : null;
        $status = $validated['status'] ?? null;

        if ($staffId && ($bankId || $agentId)) {
            return response()->json([
                'message' => 'Use either the staff filter or the bank/agent filter, not both together.',
            ], 422);
        }

        $requests = FinanceRequest::query()
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
            ->orderByDesc('id')
            ->get()
            ->map(fn (FinanceRequest $financeRequest) => $this->transformRequest($financeRequest))
            ->values();

        return response()->json([
            'filters' => [
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
            ],
            'requests' => $requests,
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $clients = $this->clientsBaseQuery()
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
            })
            ->orderByDesc('requests_count')
            ->orderBy('name')
            ->get()
            ->map(function (User $client) {
                $lastRequestAt = $client->financeRequests()->max('submitted_at');

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'is_active' => (bool) $client->is_active,
                    'requests_count' => (int) $client->requests_count,
                    'active_requests_count' => (int) $client->active_requests_count,
                    'last_request_at' => $this->iso($lastRequestAt),
                    'last_login_at' => optional($client->last_login_at)?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'summary' => [
                'total_clients' => $clients->count(),
                'clients_with_requests' => $clients->where('requests_count', '>', 0)->count(),
                'clients_with_active_requests' => $clients->where('active_requests_count', '>', 0)->count(),
            ],
            'clients' => $clients,
        ]);
    }

    public function clientRequests(User $client): JsonResponse
    {
        abort_unless($this->isClient($client), 404, 'Client not found.');

        $requests = $client->financeRequests()
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
            ->get()
            ->map(function (FinanceRequest $financeRequest) {
                return [
                    'id' => $financeRequest->id,
                    'reference_number' => $financeRequest->reference_number,
                    'approval_reference_number' => $financeRequest->approval_reference_number,
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
