<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffUserRequest;
use App\Http\Requests\Admin\UpdateStaffUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;

class StaffUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $paginator = User::query()
            ->where('account_type', UserAccountType::STAFF->value)
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'staff'))
            ->with('roles')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (User $user) => $this->serializeStaffUser($user))
                ->values(),
            'pagination' => $this->paginationMeta($paginator),
            'meta' => [
                'available_permissions' => Permission::query()
                    ->where('guard_name', 'web')
                    ->orderBy('name')
                    ->pluck('name')
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(StoreStaffUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => trim((string) $validated['name']),
            'email' => strtolower(trim((string) $validated['email'])),
            'phone' => isset($validated['phone']) && $validated['phone'] !== null ? trim((string) $validated['phone']) : null,
            'password' => (string) $validated['password'],
            'account_type' => UserAccountType::STAFF,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $user->syncRoles(['staff']);
        $user->syncPermissions($validated['permission_names'] ?? []);

        return response()->json([
            'message' => 'Staff account created successfully.',
            'data' => $this->serializeStaffUser($user->fresh()->load('roles')),
        ], 201);
    }

    public function update(UpdateStaffUserRequest $request, User $staffUser): JsonResponse
    {
        $this->ensureStaffUser($staffUser);

        $validated = $request->validated();

        $payload = [
            'name' => trim((string) $validated['name']),
            'email' => strtolower(trim((string) $validated['email'])),
            'phone' => isset($validated['phone']) && $validated['phone'] !== null ? trim((string) $validated['phone']) : null,
            'is_active' => (bool) ($validated['is_active'] ?? $staffUser->is_active),
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = (string) $validated['password'];
        }

        $staffUser->update($payload);
        $staffUser->syncRoles(['staff']);
        $staffUser->syncPermissions($validated['permission_names'] ?? []);

        return response()->json([
            'message' => 'Staff account updated successfully.',
            'data' => $this->serializeStaffUser($staffUser->fresh()->load('roles')),
        ]);
    }

    public function toggleActive(User $staffUser): JsonResponse
    {
        $this->ensureStaffUser($staffUser);

        $staffUser->update([
            'is_active' => ! $staffUser->is_active,
        ]);

        return response()->json([
            'message' => $staffUser->is_active
                ? 'Staff account activated successfully.'
                : 'Staff account deactivated successfully.',
            'data' => $this->serializeStaffUser($staffUser->fresh()->load('roles')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStaffUser(User $user): array
    {
        $directPermissions = $user->permissions()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $allPermissions = $user->getAllPermissions()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $accountType = $user->account_type instanceof UserAccountType
            ? $user->account_type->value
            : (string) $user->account_type;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'account_type' => $accountType,
            'is_active' => (bool) $user->is_active,
            'last_login_at' => optional($user->last_login_at)?->toISOString(),
            'role_names' => $user->roles->pluck('name')->values()->all(),
            'permission_names' => $directPermissions,
            'all_permission_names' => $allPermissions,
            'permissions_count' => count($directPermissions),
            'all_permissions_count' => count($allPermissions),
            'created_at' => optional($user->created_at)?->toISOString(),
            'updated_at' => optional($user->updated_at)?->toISOString(),
        ];
    }

    private function ensureStaffUser(User $user): void
    {
        $isStaff = ($user->account_type instanceof UserAccountType && $user->account_type === UserAccountType::STAFF)
            || $user->hasRole('staff');

        abort_unless($isStaff, 404);
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
