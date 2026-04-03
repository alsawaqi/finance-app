<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinanceRequestAgentAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:4000'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.agent_id' => ['required', 'integer', 'distinct', 'exists:agents,id'],
            'assignments.*.document_keys' => ['required', 'array', 'min:1'],
            'assignments.*.document_keys.*' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $assignments = collect($this->input('assignments', []))
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return [
                    'agent_id' => isset($item['agent_id']) ? (int) $item['agent_id'] : null,
                    'document_keys' => collect($item['document_keys'] ?? [])
                        ->map(fn ($value) => trim((string) $value))
                        ->filter(fn ($value) => $value !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'review_note' => $this->filled('review_note')
                ? trim((string) $this->input('review_note'))
                : null,
            'assignments' => $assignments,
        ]);
    }
}
