<?php

namespace App\Http\Requests\Concerns;

use App\Models\DocumentUploadStep;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

trait ValidatesRequiredDocumentUpload
{
    /**
     * Keep the existing fallback for older steps that do not have allowed types configured.
     *
     * @var array<int, string>
     */
    private array $defaultRequiredDocumentExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('document_upload_step_id') || $validator->errors()->has('file')) {
                return;
            }

            $step = DocumentUploadStep::query()->find((int) $this->integer('document_upload_step_id'));
            $file = $this->file('file');

            if (! $step || ! $file instanceof UploadedFile) {
                return;
            }

            $this->validateConfiguredExtension($validator, $file, $step);
            $this->validateConfiguredMaxSize($validator, $file, $step);
        });
    }

    private function validateConfiguredExtension(Validator $validator, UploadedFile $file, DocumentUploadStep $step): void
    {
        $allowedExtensions = $this->normalizeRequiredDocumentExtensions($step->allowed_file_types_json);
        $extension = strtolower(trim($file->getClientOriginalExtension()));

        if ($extension !== '' && in_array($extension, $allowedExtensions, true)) {
            return;
        }

        $validator->errors()->add(
            'file',
            'The file must be one of the following types: '.implode(', ', array_map('strtoupper', $allowedExtensions)).'.',
        );
    }

    private function validateConfiguredMaxSize(Validator $validator, UploadedFile $file, DocumentUploadStep $step): void
    {
        $maxFileSizeMb = $step->max_file_size_mb !== null
            ? (int) $step->max_file_size_mb
            : 10;

        if ($maxFileSizeMb <= 0) {
            return;
        }

        $fileSize = $file->getSize();
        $maxFileSizeBytes = $maxFileSizeMb * 1024 * 1024;

        if (is_int($fileSize) && $fileSize > $maxFileSizeBytes) {
            $validator->errors()->add('file', "The file may not be greater than {$maxFileSizeMb} MB.");
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRequiredDocumentExtensions(mixed $rawTypes): array
    {
        $extensions = collect((array) $rawTypes)
            ->flatMap(fn ($type) => $this->normalizeRequiredDocumentType($type))
            ->filter(fn (string $type) => $type !== '')
            ->unique()
            ->values()
            ->all();

        return $extensions !== []
            ? $extensions
            : $this->defaultRequiredDocumentExtensions;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRequiredDocumentType(mixed $type): array
    {
        $value = strtolower(trim((string) $type));
        $value = ltrim($value, '.');

        if ($value === '') {
            return [];
        }

        return match ($value) {
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'text/csv' => ['csv'],
            default => str_contains($value, '/')
                ? [basename(str_replace('\\', '/', $value))]
                : [$value],
        };
    }
}
