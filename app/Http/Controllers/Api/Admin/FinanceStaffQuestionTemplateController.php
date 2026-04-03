<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderFinanceStaffQuestionTemplatesRequest;
use App\Http\Requests\Admin\StoreFinanceStaffQuestionTemplateRequest;
use App\Http\Requests\Admin\UpdateFinanceStaffQuestionTemplateRequest;
use App\Models\FinanceStaffQuestionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FinanceStaffQuestionTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeTemplates(),
        ]);
    }

    public function store(StoreFinanceStaffQuestionTemplateRequest $request): JsonResponse
    {
        $template = FinanceStaffQuestionTemplate::create($request->validated());

        if (! $template->code) {
            $template->forceFill([
                'code' => 'FSQ-' . str_pad((string) $template->id, 3, '0', STR_PAD_LEFT),
            ])->save();
        }

        return response()->json([
            'message' => 'Staff question template created successfully.',
            'data' => $this->serializeTemplate($template->fresh()),
        ], 201);
    }

    public function update(UpdateFinanceStaffQuestionTemplateRequest $request, FinanceStaffQuestionTemplate $financeStaffQuestionTemplate): JsonResponse
    {
        $financeStaffQuestionTemplate->update($request->validated());

        if (! $financeStaffQuestionTemplate->code) {
            $financeStaffQuestionTemplate->forceFill([
                'code' => 'FSQ-' . str_pad((string) $financeStaffQuestionTemplate->id, 3, '0', STR_PAD_LEFT),
            ])->save();
        }

        return response()->json([
            'message' => 'Staff question template updated successfully.',
            'data' => $this->serializeTemplate($financeStaffQuestionTemplate->fresh()),
        ]);
    }

    public function toggleActive(FinanceStaffQuestionTemplate $financeStaffQuestionTemplate): JsonResponse
    {
        $financeStaffQuestionTemplate->update([
            'is_active' => ! $financeStaffQuestionTemplate->is_active,
        ]);

        return response()->json([
            'message' => $financeStaffQuestionTemplate->is_active
                ? 'Staff question template activated successfully.'
                : 'Staff question template deactivated successfully.',
            'data' => $this->serializeTemplate($financeStaffQuestionTemplate->fresh()),
        ]);
    }

    public function reorder(ReorderFinanceStaffQuestionTemplatesRequest $request): JsonResponse
    {
        $orderedIds = $request->validated('ordered_ids');

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                FinanceStaffQuestionTemplate::whereKey($id)->update([
                    'sort_order' => $index + 1,
                ]);
            }
        });

        return response()->json([
            'message' => 'Staff question template order updated successfully.',
            'data' => $this->serializeTemplates(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeTemplates(): array
    {
        return FinanceStaffQuestionTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (FinanceStaffQuestionTemplate $template) => $this->serializeTemplate($template))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(FinanceStaffQuestionTemplate $template): array
    {
        $options = $template->options_json ?? [];

        return [
            'id' => $template->id,
            'code' => $template->code,
            'question_text_en' => $template->question_text_en,
            'question_text_ar' => $template->question_text_ar,
            'question_type' => $template->question_type,
            'options_json' => $options,
            'options_count' => count($options),
            'placeholder_en' => $template->placeholder_en,
            'placeholder_ar' => $template->placeholder_ar,
            'help_text_en' => $template->help_text_en,
            'help_text_ar' => $template->help_text_ar,
            'validation_rules' => $template->validation_rules,
            'is_required' => (bool) $template->is_required,
            'sort_order' => (int) $template->sort_order,
            'is_active' => (bool) $template->is_active,
            'created_at' => optional($template->created_at)?->toISOString(),
            'updated_at' => optional($template->updated_at)?->toISOString(),
        ];
    }
}