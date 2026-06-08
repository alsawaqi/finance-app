<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRequestEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', 'unique:request_email_templates,code'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'fields_json' => ['nullable', 'array'],
            'fields_json.*.key' => ['required', 'string', 'max:80', 'distinct', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'fields_json.*.label' => ['required', 'string', 'max:255'],
            'fields_json.*.type' => ['required', 'string', Rule::in(['text', 'textarea', 'number', 'date', 'email', 'phone'])],
            'fields_json.*.required' => ['nullable', 'boolean'],
            'fields_json.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields_json.*.help_text' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $fieldKeys = collect($this->input('fields_json', []))
                ->filter(fn ($field) => is_array($field))
                ->pluck('key')
                ->map(fn ($key) => trim((string) $key))
                ->filter()
                ->unique()
                ->values();

            $definedKeys = $fieldKeys->flip();
            $usedTokens = $this->templateTokens((string) $this->input('subject', ''))
                ->merge($this->templateTokens((string) $this->input('body', '')))
                ->unique()
                ->values();

            $missingTokens = $usedTokens
                ->reject(fn (string $token) => $definedKeys->has($token))
                ->values();

            if ($missingTokens->isNotEmpty()) {
                $validator->errors()->add(
                    'fields_json',
                    'Add field definitions for these template placeholders: ' . $missingTokens->implode(', ') . '.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $fields = collect($this->input('fields_json', []))
            ->filter(fn ($field) => is_array($field))
            ->map(fn (array $field) => [
                'key' => trim((string) ($field['key'] ?? '')),
                'label' => trim((string) ($field['label'] ?? '')),
                'type' => trim((string) ($field['type'] ?? 'text')) ?: 'text',
                'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'placeholder' => $this->nullableTrim($field['placeholder'] ?? null),
                'help_text' => $this->nullableTrim($field['help_text'] ?? null),
            ])
            ->values()
            ->all();

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'code' => $this->nullableTrim($this->input('code')),
            'subject' => trim((string) $this->input('subject', '')),
            'body' => trim((string) $this->input('body', '')),
            'fields_json' => count($fields) > 0 ? $fields : null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
        ]);
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function templateTokens(string $content): \Illuminate\Support\Collection
    {
        preg_match_all('/{{\s*([A-Za-z][A-Za-z0-9_]*)\s*}}/', $content, $matches);

        return collect($matches[1] ?? []);
    }
}
