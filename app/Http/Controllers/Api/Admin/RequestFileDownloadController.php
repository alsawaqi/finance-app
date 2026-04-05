<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestFileDownloadController extends Controller
{
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
