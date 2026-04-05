<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_country_code' => ['nullable', 'string', 'max:8', 'required_with:phone', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['nullable', 'string', 'max:20', 'required_with:phone_country_code', 'regex:/^[0-9][0-9\s-]{4,19}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
