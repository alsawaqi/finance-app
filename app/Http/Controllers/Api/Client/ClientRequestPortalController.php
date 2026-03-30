<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\FinanceRequest;
use App\Services\FinanceRequestDocumentChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientRequestPortalController extends Controller
{
    public function __construct(
        private readonly FinanceRequestDocumentChecklistService $documentChecklistService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $requests = FinanceRequest::query()
            ->with(['currentContract:id,finance_request_id,status,admin_signed_at,client_signed_at'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'requests' => $requests,
        ]);
    }

    public function show(FinanceRequest $financeRequest): JsonResponse
    {
        abort_unless((int) $financeRequest->user_id === (int) auth()->id(), 403);

        $financeRequest->load([
            'client:id,name,email,phone',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments',
            'currentContract',
            'shareholders',
            'additionalDocuments',
        ]);

        return response()->json([
            'request' => $financeRequest,
            'required_documents' => $this->documentChecklistService->buildRequiredChecklist($financeRequest)->values(),
        ]);
    }
}
