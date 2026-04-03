<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class AnswerFinanceRequestStaffQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answer_text' => ['nullable', 'string', 'max:10000'],
            'answer_json' => ['nullable', 'array'],
            'answer_json.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $answerText = trim((string) $this->input('answer_text', ''));

        $answerJson = collect($this->input('answer_json', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();

        $this->merge([
            'answer_text' => $answerText !== '' ? $answerText : null,
            'answer_json' => $answerJson !== [] ? $answerJson : null,
        ]);
    }
}