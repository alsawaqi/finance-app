<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderDocumentUploadStepsRequest;
use App\Http\Requests\Admin\StoreDocumentUploadStepRequest;
use App\Http\Requests\Admin\UpdateDocumentUploadStepRequest;
use App\Models\DocumentUploadStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DocumentUploadStepController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeSteps(),
        ]);
    }

    public function store(StoreDocumentUploadStepRequest $request): JsonResponse
    {
        $step = DocumentUploadStep::create($request->validated());

        if (! $step->code) {
            $step->forceFill([
                'code' => 'DOC-STEP-' . str_pad((string) $step->id, 3, '0', STR_PAD_LEFT),
            ])->save();
        }

        return response()->json([
            'message' => 'Document upload step created successfully.',
            'data' => $this->serializeStep($step->fresh()->loadCount('requestDocumentUploads')),
        ], 201);
    }

    public function update(UpdateDocumentUploadStepRequest $request, DocumentUploadStep $documentUploadStep): JsonResponse
    {
        $documentUploadStep->update($request->validated());

        if (! $documentUploadStep->code) {
            $documentUploadStep->forceFill([
                'code' => 'DOC-STEP-' . str_pad((string) $documentUploadStep->id, 3, '0', STR_PAD_LEFT),
            ])->save();
        }

        return response()->json([
            'message' => 'Document upload step updated successfully.',
            'data' => $this->serializeStep($documentUploadStep->fresh()->loadCount('requestDocumentUploads')),
        ]);
    }

    public function destroy(DocumentUploadStep $documentUploadStep): JsonResponse
    {
        $documentUploadStep->loadCount('requestDocumentUploads');

        if ($documentUploadStep->request_document_uploads_count > 0) {
            return response()->json([
                'message' => 'This document upload step cannot be deleted because uploaded request documents already reference it.',
                'errors' => [
                    'general' => ['This document upload step cannot be deleted because uploaded request documents already reference it.'],
                ],
            ], 422);
        }

        $documentUploadStep->delete();

        return response()->json([
            'message' => 'Document upload step deleted successfully.',
        ]);
    }

    public function toggleActive(DocumentUploadStep $documentUploadStep): JsonResponse
    {
        $documentUploadStep->update([
            'is_active' => ! $documentUploadStep->is_active,
        ]);

        return response()->json([
            'message' => $documentUploadStep->is_active
                ? 'Document upload step activated successfully.'
                : 'Document upload step deactivated successfully.',
            'data' => $this->serializeStep($documentUploadStep->fresh()->loadCount('requestDocumentUploads')),
        ]);
    }

    public function reorder(ReorderDocumentUploadStepsRequest $request): JsonResponse
    {
        $orderedIds = $request->validated('ordered_ids');

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                DocumentUploadStep::whereKey($id)->update([
                    'sort_order' => $index + 1,
                ]);
            }
        });

        return response()->json([
            'message' => 'Document upload step order updated successfully.',
            'data' => $this->serializeSteps(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeSteps(): array
    {
        return DocumentUploadStep::query()
            ->withCount('requestDocumentUploads')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentUploadStep $step) => $this->serializeStep($step))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStep(DocumentUploadStep $step): array
    {
        $allowedFileTypes = $step->allowed_file_types_json ?? [];

        return [
            'id' => $step->id,
            'code' => $step->code,
            'name' => $step->name,
            'description' => $step->description,
            'is_required' => (bool) $step->is_required,
            'allowed_file_types_json' => $allowedFileTypes,
            'allowed_file_types_count' => count($allowedFileTypes),
            'max_file_size_mb' => $step->max_file_size_mb !== null ? (int) $step->max_file_size_mb : null,
            'sort_order' => (int) $step->sort_order,
            'is_active' => (bool) $step->is_active,
            'request_document_uploads_count' => (int) ($step->request_document_uploads_count ?? 0),
            'created_at' => optional($step->created_at)?->toISOString(),
            'updated_at' => optional($step->updated_at)?->toISOString(),
        ];
    }
}
