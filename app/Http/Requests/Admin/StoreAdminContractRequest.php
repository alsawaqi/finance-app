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
            'commission' => ['required', 'string', 'max:255'],
            'interest' => ['required', 'string', 'max:255'],
            'payment_period' => ['required', 'string', 'max:255'],
            'general_terms' => ['nullable', 'array'],
            'general_terms.*' => ['nullable', 'string', 'max:1000'],
            'special_terms' => ['nullable', 'string', 'max:4000'],
            'signature_data_url' => ['required', 'string'],
        ];
    }
}
