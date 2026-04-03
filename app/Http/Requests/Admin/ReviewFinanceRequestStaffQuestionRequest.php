<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewFinanceRequestStaffQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['close', 'reopen'])],
            'review_note' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => trim((string) $this->input('action', '')),
            'review_note' => $this->filled('review_note')
                ? trim((string) $this->input('review_note'))
                : null,
        ]);
    }
}