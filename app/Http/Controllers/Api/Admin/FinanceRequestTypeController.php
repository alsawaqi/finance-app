<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFinanceRequestTypeRequest;
use App\Http\Requests\Admin\UpdateFinanceRequestTypeRequest;
use App\Models\FinanceRequestType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class FinanceRequestTypeController extends Controller
{
    public function clientIndex(): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeCollection(
                FinanceRequestType::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name_en')
                    ->get()
            ),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $paginator = FinanceRequestType::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (FinanceRequestType $type) => $this->serializeItem($type))
                ->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(StoreFinanceRequestTypeRequest $request): JsonResponse
    {
        $financeRequestType = FinanceRequestType::create($this->payload($request->validated()));

        return response()->json([
            'message' => 'Finance request type created successfully.',
            'data' => $this->serializeItem($financeRequestType),
        ], 201);
    }

    public function update(UpdateFinanceRequestTypeRequest $request, FinanceRequestType $financeRequestType): JsonResponse
    {
        $financeRequestType->update($this->payload($request->validated(), $financeRequestType));

        return response()->json([
            'message' => 'Finance request type updated successfully.',
            'data' => $this->serializeItem($financeRequestType->fresh()),
        ]);
    }

    public function toggleActive(FinanceRequestType $financeRequestType): JsonResponse
    {
        $financeRequestType->update([
            'is_active' => ! $financeRequestType->is_active,
        ]);

        return response()->json([
            'message' => $financeRequestType->is_active
                ? 'Finance request type activated successfully.'
                : 'Finance request type deactivated successfully.',
            'data' => $this->serializeItem($financeRequestType->fresh()),
        ]);
    }

    private function payload(array $validated, ?FinanceRequestType $financeRequestType = null): array
    {
        $slug = trim((string) ($validated['slug'] ?? ''));
        $nameEn = trim((string) ($validated['name_en'] ?? ''));

        if ($slug === '') {
            $baseSlug = Str::slug($nameEn !== '' ? $nameEn : ('request-type-' . ($financeRequestType?->id ?? now()->timestamp)));
            $slug = $baseSlug !== '' ? $baseSlug : ('request-type-' . ($financeRequestType?->id ?? now()->timestamp));
        }

        return [
            'slug' => $slug,
            'name_en' => trim((string) ($validated['name_en'] ?? '')),
            'name_ar' => trim((string) ($validated['name_ar'] ?? '')),
            'description_en' => $this->nullableTrim($validated['description_en'] ?? null),
            'description_ar' => $this->nullableTrim($validated['description_ar'] ?? null),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
            'sort_order' => array_key_exists('sort_order', $validated) ? (int) $validated['sort_order'] : 0,
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param Collection<int, FinanceRequestType> $collection
     * @return array<int, array<string, mixed>>
     */
    private function serializeCollection(Collection $collection): array
    {
        return $collection->map(fn (FinanceRequestType $type) => $this->serializeItem($type))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(FinanceRequestType $type): array
    {
        return [
            'id' => $type->id,
            'slug' => $type->slug,
            'name_en' => $type->name_en,
            'name_ar' => $type->name_ar,
            'description_en' => $type->description_en,
            'description_ar' => $type->description_ar,
            'is_active' => (bool) $type->is_active,
            'sort_order' => (int) $type->sort_order,
            'created_at' => optional($type->created_at)?->toISOString(),
            'updated_at' => optional($type->updated_at)?->toISOString(),
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
