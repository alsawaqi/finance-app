<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdvanceFinanceRequestFromUnderstudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_note' => $this->filled('review_note')
                ? trim((string) $this->input('review_note'))
                : null,
        ]);
    }
}