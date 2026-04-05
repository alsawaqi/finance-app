<?php

namespace App\Http\Requests\Client;

use App\Models\RequestQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreClientFinanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'exists:request_questions,id'],
            'answers.*.value' => ['nullable'],

            'details' => ['required', 'array'],
            'details.finance_type' => ['required', 'in:individual,company'],
           'details.finance_request_type_id' => ['required', 'integer', 'exists:finance_request_types,id'],
            'details.country' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'details.requested_amount' => ['required', 'numeric', 'min:0'],
            'details.company_name' => ['nullable', 'string', 'max:255'],
            'details.company_cr_number' => ['nullable', 'string', 'max:255'],

            'details.email' => ['required', 'email', 'max:255'],
            'details.phone_country_code' => ['required', 'string', 'max:10'],
            'details.phone_number' => ['required', 'string', 'max:30'],
            'details.unified_number' => ['required', 'string', 'max:100'],
            'details.national_address_number' => ['required', 'string', 'max:100'],
            'details.address' => ['required', 'string', 'max:2000'],
            'details.notes' => ['nullable', 'string', 'max:2000'],

            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],

            'national_address_attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'company_cr' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

            'shareholders' => ['nullable', 'array'],
            'shareholders.*.name' => ['nullable', 'string', 'max:255'],
            'shareholders.*.phone_country_code' => ['nullable', 'string', 'max:10'],
            'shareholders.*.phone_number' => ['nullable', 'string', 'max:30'],
            'shareholders.*.id_number' => ['nullable', 'string', 'max:100'],
            'shareholders.*.id_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $financeType = $this->input('details.finance_type');

            if ($financeType === 'company') {
                if (blank($this->input('details.company_name'))) {
                    $validator->errors()->add('details.company_name', 'Company name is required when company applicant type is selected.');
                }

                if (blank($this->input('details.company_cr_number'))) {
                    $validator->errors()->add('details.company_cr_number', 'Company CR number is required when company applicant type is selected.');
                }

                if (! $this->hasFile('company_cr')) {
                    $validator->errors()->add('company_cr', 'Company CR upload is required when company applicant type is selected.');
                }

                $shareholders = $this->input('shareholders', []);
                if (! is_array($shareholders) || count($shareholders) < 1) {
                    $validator->errors()->add('shareholders', 'At least one shareholder is required when company applicant type is selected.');
                } else {
                    foreach ($shareholders as $index => $shareholder) {
                        $name = trim((string) ($shareholder['name'] ?? ''));
                        $phoneCountryCode = trim((string) ($shareholder['phone_country_code'] ?? ''));
                        $phoneNumber = trim((string) ($shareholder['phone_number'] ?? ''));
                        $idNumber = trim((string) ($shareholder['id_number'] ?? ''));

                        if ($name === '') {
                            $validator->errors()->add("shareholders.$index.name", 'Shareholder name is required.');
                        }

                        if ($phoneCountryCode === '') {
                            $validator->errors()->add("shareholders.$index.phone_country_code", 'Shareholder country code is required.');
                        }

                        if ($phoneNumber === '') {
                            $validator->errors()->add("shareholders.$index.phone_number", 'Shareholder phone number is required.');
                        }

                        if ($idNumber === '') {
                            $validator->errors()->add("shareholders.$index.id_number", 'Shareholder ID number is required.');
                        }

                        if (! $this->hasFile("shareholders.$index.id_file")) {
                            $validator->errors()->add("shareholders.$index.id_file", 'Shareholder ID upload is required.');
                        }
                    }
                }
            }

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
