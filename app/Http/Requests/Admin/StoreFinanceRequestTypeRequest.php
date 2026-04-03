<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinanceRequestTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'max:255', 'unique:finance_request_types,slug'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? trim((string) $this->input('slug')) : null,
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'description_en' => $this->filled('description_en') ? trim((string) $this->input('description_en')) : null,
            'description_ar' => $this->filled('description_ar') ? trim((string) $this->input('description_ar')) : null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
        ]);
    }
}
