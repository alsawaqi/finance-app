<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('banks', 'code')],
            'short_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'code' => $this->filled('code') ? strtoupper(trim((string) $this->input('code'))) : null,
            'short_name' => $this->filled('short_name') ? trim((string) $this->input('short_name')) : null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
