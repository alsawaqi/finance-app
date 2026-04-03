<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'smtp_username' => ['nullable', 'email', 'max:255'],
            'smtp_sender_name' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'remove_smtp_password' => ['nullable', 'boolean'],
        ];
    }
}
