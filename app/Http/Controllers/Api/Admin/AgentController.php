<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgentRequest;
use App\Http\Requests\Admin\UpdateAgentRequest;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;

class AgentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeAgents(),
        ]);
    }

    public function store(StoreAgentRequest $request): JsonResponse
    {
        $agent = Agent::create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Agent created successfully.',
            'data' => $this->serializeAgent($agent->fresh(['creator:id,name,email', 'bank:id,name,short_name,code'])),
        ], 201);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse
    {
        $agent->update($request->validated());

        return response()->json([
            'message' => 'Agent updated successfully.',
            'data' => $this->serializeAgent($agent->fresh(['creator:id,name,email', 'bank:id,name,short_name,code'])),
        ]);
    }

    public function toggleActive(Agent $agent): JsonResponse
    {
        $agent->update([
            'is_active' => ! $agent->is_active,
        ]);

        return response()->json([
            'message' => $agent->is_active
                ? 'Agent activated successfully.'
                : 'Agent deactivated successfully.',
            'data' => $this->serializeAgent($agent->fresh(['creator:id,name,email', 'bank:id,name,short_name,code'])),
        ]);
    }

    private function serializeAgents(): array
    {
        return Agent::query()
            ->with(['creator:id,name,email', 'bank:id,name,short_name,code'])
            ->orderBy('name')
            ->get()
            ->map(fn (Agent $agent) => $this->serializeAgent($agent))
            ->all();
    }

    private function serializeAgent(Agent $agent): array
    {
        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'phone' => $agent->phone,
            'company_name' => $agent->company_name,
            'bank_id' => $agent->bank_id,
            'bank_name' => $agent->bank?->name,
            'bank_short_name' => $agent->bank?->short_name,
            'bank_code' => $agent->bank?->code,
            'agent_type' => $agent->agent_type,
            'notes' => $agent->notes,
            'is_active' => (bool) $agent->is_active,
            'created_by' => $agent->created_by,
            'creator_name' => $agent->creator?->name,
            'created_at' => optional($agent->created_at)?->toISOString(),
            'updated_at' => optional($agent->updated_at)?->toISOString(),
        ];
    }
}
