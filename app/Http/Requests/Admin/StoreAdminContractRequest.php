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
            'contract_template_slug' => ['required', 'string', 'max:120'],
            'contract_body_html' => ['required', 'string'],
            'signature_data_url' => ['required', 'string'],
        ];
    }
}
