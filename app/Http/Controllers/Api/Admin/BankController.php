<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBankRequest;
use App\Http\Requests\Admin\UpdateBankRequest;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;

class BankController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeBanks(),
        ]);
    }

    public function store(StoreBankRequest $request): JsonResponse
    {
        $bank = Bank::create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Bank created successfully.',
            'data' => $this->serializeBank($bank->fresh(['creator:id,name,email'])->loadCount('agents')),
        ], 201);
    }

    public function update(UpdateBankRequest $request, Bank $bank): JsonResponse
    {
        $bank->update($request->validated());

        return response()->json([
            'message' => 'Bank updated successfully.',
            'data' => $this->serializeBank($bank->fresh(['creator:id,name,email'])->loadCount('agents')),
        ]);
    }

    public function toggleActive(Bank $bank): JsonResponse
    {
        $bank->update([
            'is_active' => ! $bank->is_active,
        ]);

        return response()->json([
            'message' => $bank->is_active
                ? 'Bank activated successfully.'
                : 'Bank deactivated successfully.',
            'data' => $this->serializeBank($bank->fresh(['creator:id,name,email'])->loadCount('agents')),
        ]);
    }

    private function serializeBanks(): array
    {
        return Bank::query()
            ->with(['creator:id,name,email'])
            ->withCount('agents')
            ->orderBy('name')
            ->get()
            ->map(fn (Bank $bank) => $this->serializeBank($bank))
            ->all();
    }

    private function serializeBank(Bank $bank): array
    {
        return [
            'id' => $bank->id,
            'name' => $bank->name,
            'code' => $bank->code,
            'short_name' => $bank->short_name,
            'is_active' => (bool) $bank->is_active,
            'agents_count' => (int) ($bank->agents_count ?? 0),
            'created_by' => $bank->created_by,
            'creator_name' => $bank->creator?->name,
            'created_at' => optional($bank->created_at)?->toISOString(),
            'updated_at' => optional($bank->updated_at)?->toISOString(),
        ];
    }
}
