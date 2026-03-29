<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignStaffToFinanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where(function ($inner) {
                        $inner->where('account_type', 'staff')
                            ->orWhereExists(function ($roleQuery) {
                                $roleQuery->selectRaw('1')
                                    ->from('model_has_roles')
                                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                                    ->whereColumn('model_has_roles.model_id', 'users.id')
                                    ->where('model_has_roles.model_type', User::class)
                                    ->where('roles.name', 'staff');
                            });
                    });
                }),
            ],
            'primary_staff_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $staffIds = collect($this->input('staff_ids', []))->map(fn ($id) => (int) $id)->all();
            $primaryStaffId = $this->filled('primary_staff_id') ? (int) $this->input('primary_staff_id') : null;

            if ($primaryStaffId !== null && ! in_array($primaryStaffId, $staffIds, true)) {
                $validator->errors()->add('primary_staff_id', 'The primary staff member must be one of the selected staff members.');
            }
        });
    }
}
