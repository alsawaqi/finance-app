<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_template_slug' => ['required_without:uploaded_contract_file', 'nullable', 'string', 'max:120'],
            'contract_body_html' => ['required_without:uploaded_contract_file', 'nullable', 'string'],
            'signature_data_url' => ['required_without:uploaded_contract_file', 'nullable', 'string'],
            'uploaded_contract_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'requires_commercial_registration' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('requires_commercial_registration')) {
            $this->merge([
                'requires_commercial_registration' => filter_var($this->input('requires_commercial_registration'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
