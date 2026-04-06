<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentUploadStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $stepId = $this->route('documentUploadStep')?->id;

        return [
            'code' => ['nullable', 'string', 'max:255', Rule::unique('document_upload_steps', 'code')->ignore($stepId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_required' => ['sometimes', 'boolean'],
            'is_multiple' => ['sometimes', 'boolean'],
            'allowed_file_types_json' => ['nullable', 'array'],
            'allowed_file_types_json.*' => ['string', 'max:100'],
            'max_file_size_mb' => ['nullable', 'integer', 'min:1', 'max:10240'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? trim((string) $this->input('code')) : null,
            'name' => trim((string) $this->input('name', '')),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'is_required' => $this->boolean('is_required'),
            'is_multiple' => $this->boolean('is_multiple'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'max_file_size_mb' => $this->filled('max_file_size_mb') ? (int) $this->input('max_file_size_mb') : null,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
            'allowed_file_types_json' => $this->normalizeAllowedTypes($this->input('allowed_file_types_json')),
        ]);
    }

    private function normalizeAllowedTypes(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $items = array_values(array_unique(array_filter(array_map(
            fn ($item) => is_string($item) ? trim($item) : '',
            $value,
        ))));

        return $items === [] ? null : $items;
    }
}
