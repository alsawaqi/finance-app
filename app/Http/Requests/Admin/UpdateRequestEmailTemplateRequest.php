<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateRequestEmailTemplateRequest extends StoreRequestEmailTemplateRequest
{
    public function rules(): array
    {
        $requestEmailTemplate = $this->route('requestEmailTemplate');
        $templateId = is_object($requestEmailTemplate) ? $requestEmailTemplate->getKey() : $requestEmailTemplate;

        return [
            ...parent::rules(),
            'code' => ['nullable', 'string', 'max:255', Rule::unique('request_email_templates', 'code')->ignore($templateId)],
        ];
    }
}
