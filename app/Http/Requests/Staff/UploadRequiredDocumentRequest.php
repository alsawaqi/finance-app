<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Concerns\ValidatesRequiredDocumentUpload;
use Illuminate\Foundation\Http\FormRequest;

class UploadRequiredDocumentRequest extends FormRequest
{
    use ValidatesRequiredDocumentUpload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_upload_step_id' => ['required', 'integer', 'exists:document_upload_steps,id'],
            'file' => ['required', 'file'],
        ];
    }
}
