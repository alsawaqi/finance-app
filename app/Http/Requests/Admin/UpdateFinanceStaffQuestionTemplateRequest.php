<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinanceStaffQuestionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $financeStaffQuestionTemplate = $this->route('financeStaffQuestionTemplate');
        $templateId = is_object($financeStaffQuestionTemplate) ? $financeStaffQuestionTemplate->getKey() : $financeStaffQuestionTemplate;

        return [
            'code' => ['nullable', 'string', 'max:255', Rule::unique('finance_staff_question_templates', 'code')->ignore($templateId)],
            'question_text_en' => ['required', 'string'],
            'question_text_ar' => ['nullable', 'string'],
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
            'options_json' => [
                Rule::requiredIf(fn () => in_array($this->input('question_type'), ['select', 'radio', 'checkbox'], true)),
                'nullable',
                'array',
                'min:1',
            ],
            'options_json.*' => ['required', 'string', 'max:255'],
            'placeholder_en' => ['nullable', 'string', 'max:255'],
            'placeholder_ar' => ['nullable', 'string', 'max:255'],
            'help_text_en' => ['nullable', 'string'],
            'help_text_ar' => ['nullable', 'string'],
            'validation_rules' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = trim((string) $this->input('code', ''));
        $placeholderEn = trim((string) $this->input('placeholder_en', ''));
        $placeholderAr = trim((string) $this->input('placeholder_ar', ''));
        $helpTextEn = trim((string) $this->input('help_text_en', ''));
        $helpTextAr = trim((string) $this->input('help_text_ar', ''));
        $validationRules = trim((string) $this->input('validation_rules', ''));

        $options = collect($this->input('options_json', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();

        $this->merge([
            'code' => $code !== '' ? $code : null,
            'question_text_en' => trim((string) $this->input('question_text_en', '')),
            'question_text_ar' => $this->nullableTrim($this->input('question_text_ar')),
            'placeholder_en' => $placeholderEn !== '' ? $placeholderEn : null,
            'placeholder_ar' => $placeholderAr !== '' ? $placeholderAr : null,
            'help_text_en' => $helpTextEn !== '' ? $helpTextEn : null,
            'help_text_ar' => $helpTextAr !== '' ? $helpTextAr : null,
            'validation_rules' => $validationRules !== '' ? $validationRules : null,
            'options_json' => count($options) > 0 ? $options : null,
            'is_required' => $this->boolean('is_required'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : false,
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
}