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

    private function downloadStoredFile(string $disk, ?string $path, ?string $filename): StreamedResponse
    {
        abort_unless(filled($path), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $filename ?: basename($path));
    }
}
