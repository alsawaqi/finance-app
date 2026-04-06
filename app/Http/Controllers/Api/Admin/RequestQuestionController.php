<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderRequestQuestionsRequest;
use App\Http\Requests\Admin\StoreRequestQuestionRequest;
use App\Http\Requests\Admin\UpdateRequestQuestionRequest;
use App\Models\RequestQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RequestQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $paginator = RequestQuestion::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (RequestQuestion $question) => $this->serializeQuestion($question))
                ->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(StoreRequestQuestionRequest $request): JsonResponse
    {
        $question = RequestQuestion::create($request->validated());

        if (! $question->code) {
            $question->forceFill([
                'code' => 'RQ-QUESTION-' . str_pad((string) $question->id, 3, '0', STR_PAD_LEFT),
            ])->save();
        }

        return response()->json([
            'message' => 'Request question created successfully.',
            'data' => $this->serializeQuestion($question->fresh()),
        ], 201);
    }

    public function update(UpdateRequestQuestionRequest $request, RequestQuestion $requestQuestion): JsonResponse
    {
        $requestQuestion->update($request->validated());

        if (! $requestQuestion->code) {
            $requestQuestion->forceFill([
                'code' => 'RQ-QUESTION-' . str_pad((string) $requestQuestion->id, 3, '0', STR_PAD_LEFT),
            ])->save();
        }

        return response()->json([
            'message' => 'Request question updated successfully.',
            'data' => $this->serializeQuestion($requestQuestion->fresh()),
        ]);
    }

    public function toggleActive(RequestQuestion $requestQuestion): JsonResponse
    {
        $requestQuestion->update([
            'is_active' => ! $requestQuestion->is_active,
        ]);

        return response()->json([
            'message' => $requestQuestion->is_active
                ? 'Request question activated successfully.'
                : 'Request question deactivated successfully.',
            'data' => $this->serializeQuestion($requestQuestion->fresh()),
        ]);
    }

    public function reorder(ReorderRequestQuestionsRequest $request): JsonResponse
    {
        $orderedIds = $request->validated('ordered_ids');

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                RequestQuestion::whereKey($id)->update([
                    'sort_order' => $index + 1,
                ]);
            }
        });

        return response()->json([
            'message' => 'Request question order updated successfully.',
            'data' => $this->serializeQuestions(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeQuestions(): array
    {
        return RequestQuestion::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (RequestQuestion $question) => $this->serializeQuestion($question))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeQuestion(RequestQuestion $question): array
    {
        $options = $question->options_json ?? [];

        return [
            'id' => $question->id,
            'code' => $question->code,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'finance_type' => $question->finance_type ?? 'all',
            'options_json' => $options,
            'options_count' => count($options),
            'placeholder' => $question->placeholder,
            'help_text' => $question->help_text,
            'validation_rules' => $question->validation_rules,
            'is_required' => (bool) $question->is_required,
            'sort_order' => (int) $question->sort_order,
            'is_active' => (bool) $question->is_active,
            'created_at' => optional($question->created_at)?->toISOString(),
            'updated_at' => optional($question->updated_at)?->toISOString(),
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
