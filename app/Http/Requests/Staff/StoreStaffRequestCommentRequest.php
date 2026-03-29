<?php

namespace App\Http\Requests\Staff;

use App\Enums\RequestCommentVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequestCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment_text' => ['required', 'string', 'max:4000'],
            'visibility' => ['nullable', 'string', Rule::in(array_map(static fn (RequestCommentVisibility $item) => $item->value, RequestCommentVisibility::cases()))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comment_text' => trim((string) $this->input('comment_text', '')),
            'visibility' => $this->filled('visibility') ? trim((string) $this->input('visibility')) : RequestCommentVisibility::INTERNAL->value,
        ]);
    }
}
