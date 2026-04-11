<?php

namespace App\Http\Requests\Admin;

use App\Enums\FinanceRequestWorkflowStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinanceRequestWorkflowStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workflow_stage' => ['required', 'string', Rule::enum(FinanceRequestWorkflowStage::class)],
        ];
    }
}
