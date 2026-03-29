<?php

namespace App\Http\Requests\Client;

use App\Models\RequestQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreClientFinanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $decodedAnswers = $this->input('answers');

        if ($this->filled('answers_json')) {
            $decoded = json_decode((string) $this->input('answers_json'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decodedAnswers = $decoded;
            }
        }

        if (! is_array($decodedAnswers)) {
            $decodedAnswers = [];
        }

        $this->merge([
            'answers' => $decodedAnswers,
        ]);
    }

    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'exists:request_questions,id'],
            'answers.*.value' => ['nullable'],

            'details' => ['required', 'array'],
            'details.name' => ['required', 'string', 'max:255'],
            'details.country' => ['required', 'string', 'max:120'],
            'details.requested_amount' => ['required', 'numeric', 'min:0'],
            'details.finance_type' => ['required', 'in:individual,company'],
            'details.notes' => ['nullable', 'string', 'max:2000'],

            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $questions = RequestQuestion::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('id');

            $answers = collect($this->input('answers', []))
                ->keyBy(fn ($answer) => (int) ($answer['question_id'] ?? 0));

            foreach ($questions as $questionId => $question) {
                $submitted = $answers->get((int) $questionId);
                $value = $submitted['value'] ?? null;

                if ($question->is_required && ! $this->hasAnswerValue($value)) {
                    $validator->errors()->add("answers.$questionId", "{$question->question_text} is required.");
                    continue;
                }

                if (! $this->hasAnswerValue($value)) {
                    continue;
                }

                if (in_array($question->question_type, ['select', 'radio'], true)) {
                    $allowed = collect($question->options_json ?? [])->map(fn ($option) => (string) $option)->all();
                    if (! in_array((string) $value, $allowed, true)) {
                        $validator->errors()->add("answers.$questionId", "Invalid selection for {$question->question_text}.");
                    }
                }

                if ($question->question_type === 'checkbox') {
                    if (! is_array($value)) {
                        $validator->errors()->add("answers.$questionId", "{$question->question_text} must be an array of values.");
                        continue;
                    }

                    $allowed = collect($question->options_json ?? [])->map(fn ($option) => (string) $option)->all();
                    foreach ($value as $item) {
                        if (! in_array((string) $item, $allowed, true)) {
                            $validator->errors()->add("answers.$questionId", "Invalid selection for {$question->question_text}.");
                            break;
                        }
                    }
                }

                if (in_array($question->question_type, ['number', 'currency'], true) && ! is_numeric($value)) {
                    $validator->errors()->add("answers.$questionId", "{$question->question_text} must be a valid number.");
                }

                if ($question->question_type === 'email' && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false) {
                    $validator->errors()->add("answers.$questionId", "{$question->question_text} must be a valid email address.");
                }

                if ($question->question_type === 'date' && strtotime((string) $value) === false) {
                    $validator->errors()->add("answers.$questionId", "{$question->question_text} must be a valid date.");
                }
            }
        });
    }

    private function hasAnswerValue(mixed $value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => trim((string) $item) !== '')) > 0;
        }

        return $value !== null && trim((string) $value) !== '';
    }
}
