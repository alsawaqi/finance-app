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
            'document_keys' => ['nullable', 'array'],
            'document_keys.*' => ['string', 'distinct', 'max:255'],
            'email_template_id' => ['nullable', 'integer', 'exists:request_email_templates,id'],
            'template_values' => ['nullable', 'array'],
            'template_values.*' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'required_without:email_template_id', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $templateValues = is_array($this->input('template_values'))
            ? collect($this->input('template_values'))
                ->mapWithKeys(fn ($value, $key) => [(string) $key => trim((string) $value)])
                ->all()
            : [];

        $this->merge([
            'bank_id' => $this->filled('bank_id') ? (int) $this->input('bank_id') : null,
            'agent_id' => (int) $this->input('agent_id'),
            'email_template_id' => $this->filled('email_template_id') ? (int) $this->input('email_template_id') : null,
            'template_values' => $templateValues,
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
