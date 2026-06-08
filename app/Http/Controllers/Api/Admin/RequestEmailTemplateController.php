<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRequestEmailTemplateRequest;
use App\Http\Requests\Admin\UpdateRequestEmailTemplateRequest;
use App\Models\RequestEmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class RequestEmailTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'active_only' => ['nullable', 'boolean'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);

        $paginator = RequestEmailTemplate::query()
            ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
            ->with('creator:id,name,email')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (RequestEmailTemplate $template) => $this->serializeTemplate($template))
                ->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(StoreRequestEmailTemplateRequest $request): JsonResponse
    {
        $template = RequestEmailTemplate::create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
        ]);

        if (! $template->code) {
            $template->forceFill([
                'code' => $this->autoCode($template),
            ])->save();
        }

        return response()->json([
            'message' => 'Request email template created successfully.',
            'data' => $this->serializeTemplate($template->fresh('creator:id,name,email')),
        ], 201);
    }

    public function update(UpdateRequestEmailTemplateRequest $request, RequestEmailTemplate $requestEmailTemplate): JsonResponse
    {
        $requestEmailTemplate->update($request->validated());

        if (! $requestEmailTemplate->code) {
            $requestEmailTemplate->forceFill([
                'code' => $this->autoCode($requestEmailTemplate),
            ])->save();
        }

        return response()->json([
            'message' => 'Request email template updated successfully.',
            'data' => $this->serializeTemplate($requestEmailTemplate->fresh('creator:id,name,email')),
        ]);
    }

    public function toggleActive(RequestEmailTemplate $requestEmailTemplate): JsonResponse
    {
        $requestEmailTemplate->update([
            'is_active' => ! $requestEmailTemplate->is_active,
        ]);

        return response()->json([
            'message' => $requestEmailTemplate->is_active
                ? 'Request email template activated successfully.'
                : 'Request email template deactivated successfully.',
            'data' => $this->serializeTemplate($requestEmailTemplate->fresh('creator:id,name,email')),
        ]);
    }

    private function autoCode(RequestEmailTemplate $template): string
    {
        $slug = Str::slug($template->name);

        return ($slug !== '' ? $slug : 'request-email-template') . '-' . str_pad((string) $template->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(RequestEmailTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'code' => $template->code,
            'subject' => $template->subject,
            'body' => $template->body,
            'fields_json' => $template->fields_json ?? [],
            'fields_count' => count($template->fields_json ?? []),
            'sort_order' => (int) $template->sort_order,
            'is_active' => (bool) $template->is_active,
            'created_by' => $template->created_by,
            'creator' => $template->creator ? [
                'id' => $template->creator->id,
                'name' => $template->creator->name,
                'email' => $template->creator->email,
            ] : null,
            'created_at' => optional($template->created_at)?->toISOString(),
            'updated_at' => optional($template->updated_at)?->toISOString(),
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
