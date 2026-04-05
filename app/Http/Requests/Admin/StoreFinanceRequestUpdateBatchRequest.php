<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFinanceRequestUpdateBatchRequest extends FormRequest
{
    private const INTAKE_FIELD_KEYS = [
        'finance_request_type_id',
        'country',
        'country_code',
        'requested_amount',
        'company_name',
        'company_cr_number',
        'email',
        'phone_country_code',
        'phone_number',
        'unified_number',
        'national_address_number',
        'address',
        'notes',
    ];

    private const ATTACHMENT_KEYS = [
        'national_address_attachment',
        'company_cr',
        'initial_submission',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason_en' => ['nullable', 'string', 'max:2000'],
            'reason_ar' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.item_type' => ['required', 'string', 'in:intake_field,request_answer,attachment'],
            'items.*.field_key' => ['nullable', 'string', 'max:100'],
            'items.*.question_id' => ['nullable', 'integer', 'exists:request_questions,id'],
            'items.*.label_en' => ['nullable', 'string', 'max:255'],
            'items.*.label_ar' => ['nullable', 'string', 'max:255'],
            'items.*.instruction_en' => ['nullable', 'string', 'max:2000'],
            'items.*.instruction_ar' => ['nullable', 'string', 'max:2000'],
            'items.*.editable_by' => ['nullable', 'string', 'in:client,both'],
            'items.*.is_required' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $itemType = (string) ($item['item_type'] ?? '');
                $fieldKey = (string) ($item['field_key'] ?? '');
                $questionId = $item['question_id'] ?? null;

                if ($itemType === 'intake_field' && ! in_array($fieldKey, self::INTAKE_FIELD_KEYS, true)) {
                    $validator->errors()->add("items.$index.field_key", 'Invalid intake field key for update request item.');
                }

                if ($itemType === 'request_answer' && blank($questionId)) {
                    $validator->errors()->add("items.$index.question_id", 'Question selection is required for request answer update items.');
                }

                if ($itemType === 'attachment' && ! in_array($fieldKey, self::ATTACHMENT_KEYS, true)) {
                    $validator->errors()->add("items.$index.field_key", 'Invalid attachment field key for update request item.');
                }
            }
        });
    }
}
