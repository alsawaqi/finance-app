<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class SendFinanceRequestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'document_keys' => ['required', 'array', 'min:1'],
            'document_keys.*' => ['required', 'string', 'distinct', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_id' => $this->filled('bank_id') ? (int) $this->input('bank_id') : null,
            'agent_id' => (int) $this->input('agent_id'),
            'document_keys' => collect($this->input('document_keys', []))
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->values()
                ->all(),
            'subject' => trim((string) $this->input('subject', '')),
            'body' => $this->filled('body') ? trim((string) $this->input('body')) : null,
        ]);
    }
}
