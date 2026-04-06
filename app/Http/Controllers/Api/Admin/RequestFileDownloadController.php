<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentUploadStep;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestShareholder;
use App\Models\RequestAdditionalDocument;
use App\Models\RequestAttachment;
use App\Models\RequestDocumentUpload;
use App\Models\RequestEmail;
use App\Models\RequestEmailAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class RequestFileDownloadController extends Controller
{
    public function attachmentBundle(Request $request, FinanceRequest $financeRequest): BinaryFileResponse
    {
        $this->authorizeRequestDownload($request->user(), $financeRequest);

        $attachments = RequestAttachment::query()
            ->where('finance_request_id', $financeRequest->id)
            ->orderByDesc('id')
            ->get();

        abort_if($attachments->isEmpty(), 404, 'No request attachments are available for download.');

        $entries = $attachments
            ->map(fn (RequestAttachment $attachment, int $index): array => [
                'disk' => $attachment->disk ?: 'public',
                'path' => $attachment->file_path,
                'filename' => $this->buildArchiveEntryName(
                    $index + 1,
                    $attachment->file_name ?: ('request-attachment-' . $attachment->id)
                ),
            ])
            ->all();

        return $this->downloadZipFromEntries(
            $entries,
            'request-attachments-' . $financeRequest->reference_number . '.zip'
        );
    }

    public function attachment(Request $request, FinanceRequest $financeRequest, RequestAttachment $attachment): StreamedResponse
    {
        $this->authorizeRequestDownload($request->user(), $financeRequest);
        abort_unless((int) $attachment->finance_request_id === (int) $financeRequest->id, 404);

        return $this->downloadStoredFile(
            disk: $attachment->disk ?: 'public',
            path: $attachment->file_path,
            filename: $attachment->file_name,
            preview: $request->boolean('preview'),
        );
    }

    public function shareholderId(Request $request, FinanceRequest $financeRequest, FinanceRequestShareholder $shareholder): StreamedResponse
    {
        $this->authorizeRequestDownload($request->user(), $financeRequest);
        abort_unless((int) $shareholder->finance_request_id === (int) $financeRequest->id, 404);

        return $this->downloadStoredFile(
            disk: $shareholder->disk ?: 'public',
            path: $shareholder->id_file_path,
            filename: $shareholder->id_file_name,
            preview: $request->boolean('preview'),
        );
    }

    public function requiredDocument(Request $request, FinanceRequest $financeRequest, RequestDocumentUpload $requestDocumentUpload): StreamedResponse
    {
        $this->authorizeRequestDownload($request->user(), $financeRequest);
        abort_unless((int) $requestDocumentUpload->finance_request_id === (int) $financeRequest->id, 404);

        return $this->downloadStoredFile(
            disk: $requestDocumentUpload->disk ?: 'public',
            path: $requestDocumentUpload->file_path,
            filename: $requestDocumentUpload->file_name,
            preview: $request->boolean('preview'),
        );
    }

    public function requiredDocumentBundle(
        Request $request,
        FinanceRequest $financeRequest,
        DocumentUploadStep $documentUploadStep
    ): BinaryFileResponse {
        $this->authorizeRequestDownload($request->user(), $financeRequest);

        $uploads = RequestDocumentUpload::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('document_upload_step_id', $documentUploadStep->id)
            ->orderByDesc('id')
            ->get();

        abort_if($uploads->isEmpty(), 404, 'No uploaded files are available for this required document step.');

        $entries = $uploads
            ->map(fn (RequestDocumentUpload $upload, int $index): array => [
                'disk' => $upload->disk ?: 'public',
                'path' => $upload->file_path,
                'filename' => $this->buildArchiveEntryName(
                    $index + 1,
                    $upload->file_name ?: ('required-document-' . $upload->id),
                    $upload->status?->value ?? (string) $upload->status
                ),
            ])
            ->all();

        $stepCode = $documentUploadStep->code ?: ('step-' . $documentUploadStep->id);

        return $this->downloadZipFromEntries(
            $entries,
            sprintf(
                'required-documents-%s-%s.zip',
                $financeRequest->reference_number,
                $this->sanitizeArchiveName($stepCode)
            )
        );
    }

    public function additionalDocument(Request $request, FinanceRequest $financeRequest, RequestAdditionalDocument $additionalDocument): StreamedResponse
    {
        $this->authorizeRequestDownload($request->user(), $financeRequest);
        abort_unless((int) $additionalDocument->finance_request_id === (int) $financeRequest->id, 404);

        return $this->downloadStoredFile(
            disk: $additionalDocument->disk ?: 'public',
            path: $additionalDocument->file_path,
            filename: $additionalDocument->file_name ?: ($additionalDocument->title ?: 'additional-document'),
            preview: $request->boolean('preview'),
        );
    }

    public function emailAttachment(Request $request, FinanceRequest $financeRequest, RequestEmail $requestEmail, RequestEmailAttachment $requestEmailAttachment): StreamedResponse
    {
        $this->authorizeRequestDownload($request->user(), $financeRequest);
        abort_unless((int) $requestEmail->finance_request_id === (int) $financeRequest->id, 404);
        abort_unless((int) $requestEmailAttachment->request_email_id === (int) $requestEmail->id, 404);

        return $this->downloadStoredFile(
            disk: $requestEmailAttachment->disk ?: 'public',
            path: $requestEmailAttachment->file_path,
            filename: $requestEmailAttachment->file_name ?: 'request-email-attachment',
            preview: $request->boolean('preview'),
        );
    }

    private function authorizeRequestDownload(?User $user, FinanceRequest $financeRequest): void
    {
        abort_unless($user, 403);

        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless($user->hasRole('staff') && $user->can('view assigned requests'), 403);

        $isAssigned = (int) $financeRequest->primary_staff_id === (int) $user->id
            || $financeRequest->assignments()
                ->where('staff_id', $user->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($isAssigned, 403, 'You are not assigned to this request.');
    }

    private function downloadStoredFile(string $disk, ?string $path, ?string $filename, bool $preview = false): StreamedResponse
    {
        abort_unless(filled($path), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $resolvedFilename = $filename ?: basename($path);

        if ($preview) {
            $mimeType = $this->detectMimeType($disk, $path, $resolvedFilename);

            if ($this->isPreviewableMimeType($mimeType)) {
                return Storage::disk($disk)->response(
                    $path,
                    $resolvedFilename,
                    [
                        'Content-Type' => $mimeType,
                        'X-Content-Type-Options' => 'nosniff',
                    ],
                    'inline'
                );
            }
        }

        return Storage::disk($disk)->download($path, $resolvedFilename);
    }

    /**
     * @param  array<int, array{disk:string,path:string,filename:string}>  $entries
     */
    private function downloadZipFromEntries(array $entries, string $archiveFilename): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'finance-req-');
        abort_if($tempPath === false, 500, 'Unable to prepare archive download.');

        $zip = new ZipArchive();
        $opened = $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            @unlink($tempPath);
            abort(500, 'Unable to create archive.');
        }

        foreach ($entries as $entry) {
            $disk = (string) ($entry['disk'] ?? 'public');
            $path = (string) ($entry['path'] ?? '');

            if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $entryFilename = $this->sanitizeArchiveName((string) ($entry['filename'] ?? basename($path)));
            $zip->addFromString($entryFilename, Storage::disk($disk)->get($path));
        }

        $addedFiles = $zip->numFiles;
        $zip->close();

        if ($addedFiles === 0) {
            @unlink($tempPath);
            abort(404, 'No downloadable files were found in storage.');
        }

        $safeArchiveFilename = $this->sanitizeArchiveName($archiveFilename);
        if (! str_ends_with(strtolower($safeArchiveFilename), '.zip')) {
            $safeArchiveFilename .= '.zip';
        }

        return response()
            ->download($tempPath, $safeArchiveFilename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function buildArchiveEntryName(int $position, string $filename, ?string $status = null): string
    {
        $prefix = str_pad((string) $position, 2, '0', STR_PAD_LEFT);
        $safeFilename = $this->sanitizeArchiveName($filename);

        if (filled($status)) {
            return $prefix . '-' . $this->sanitizeArchiveName($status) . '-' . $safeFilename;
        }

        return $prefix . '-' . $safeFilename;
    }

    private function sanitizeArchiveName(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value)) ?? '';
        $sanitized = trim($sanitized, '-.');

        return $sanitized !== '' ? $sanitized : 'file';
    }

    private function detectMimeType(string $disk, string $path, string $filename): string
    {
        $mimeType = Storage::disk($disk)->mimeType($path);

        if (is_string($mimeType) && $mimeType !== '') {
            return $mimeType;
        }

        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'txt', 'log' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }

    private function isPreviewableMimeType(string $mimeType): bool
    {
        if (str_starts_with($mimeType, 'image/')) {
            return true;
        }

        return in_array($mimeType, [
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/json',
        ], true);
    }
}
