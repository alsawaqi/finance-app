<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:255', 'unique:request_questions,code'],
            'question_text' => ['required', 'string'],
            'question_type' => ['required', 'string', Rule::in([
                'text',
                'textarea',
                'select',
                'radio',
                'checkbox',
                'number',
                'date',
                'email',
                'phone',
                'currency',
            ])],
            'finance_type' => ['nullable', 'string', Rule::in(['all', 'individual', 'company'])],
            'options_json' => [
                Rule::requiredIf(fn () => in_array($this->input('question_type'), ['select', 'radio', 'checkbox'], true)),
                'nullable',
                'array',
                'min:1',
            ],
            'options_json.*' => ['required', 'string', 'max:255'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string'],
            'validation_rules' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = trim((string) $this->input('code', ''));
        $placeholder = trim((string) $this->input('placeholder', ''));
        $helpText = trim((string) $this->input('help_text', ''));
        $validationRules = trim((string) $this->input('validation_rules', ''));
        $financeType = strtolower(trim((string) $this->input('finance_type', 'all')));

        $options = collect($this->input('options_json', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();

        $this->merge([
            'code' => $code !== '' ? $code : null,
            'placeholder' => $placeholder !== '' ? $placeholder : null,
            'help_text' => $helpText !== '' ? $helpText : null,
            'validation_rules' => $validationRules !== '' ? $validationRules : null,
            'finance_type' => $financeType !== '' ? $financeType : 'all',
            'options_json' => count($options) > 0 ? $options : null,
            'is_required' => $this->boolean('is_required'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
        ]);
    }
}
