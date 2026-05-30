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
            'finance_type' => ['nullable', 'string', Rule::in(['all', 'individual', 'company'])],
            'description' => ['nullable', 'string'],
            'is_required' => ['sometimes', 'boolean'],
            'is_multiple' => ['sometimes', 'boolean'],
            'allowed_file_types_json' => ['nullable', 'array'],
            'allowed_file_types_json.*' => ['string', 'max:100'],
            'max_file_size_mb' => ['nullable', 'integer', 'min:1', 'max:10240'],
            'max_file_size_kb' => ['nullable', 'integer', 'min:1', 'max:10485760'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $maxFileSizeKb = $this->normalizeMaxFileSizeKb(
            $this->input('max_file_size_kb'),
            $this->input('max_file_size_mb'),
        );

        $this->merge([
            'code' => $this->filled('code') ? trim((string) $this->input('code')) : null,
            'name' => trim((string) $this->input('name', '')),
            'finance_type' => $this->normalizeFinanceType($this->input('finance_type')),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'is_required' => $this->boolean('is_required'),
            'is_multiple' => $this->boolean('is_multiple'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'max_file_size_mb' => $maxFileSizeKb !== null ? (int) ceil($maxFileSizeKb / 1024) : null,
            'max_file_size_kb' => $maxFileSizeKb,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
            'allowed_file_types_json' => $this->normalizeAllowedTypes($this->input('allowed_file_types_json')),
        ]);
    }

    private function normalizeMaxFileSizeKb(mixed $kilobytes, mixed $megabytes): ?int
    {
        if ($kilobytes !== null && $kilobytes !== '') {
            return (int) round((float) $kilobytes);
        }

        if ($megabytes !== null && $megabytes !== '') {
            return (int) round((float) $megabytes * 1024);
        }

        return null;
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

    private function normalizeFinanceType(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['individual', 'company'], true)
            ? $normalized
            : 'all';
    }
}
