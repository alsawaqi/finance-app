<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SignClientContractRequest;
use App\Models\FinanceRequest;
use App\Models\RequestTimeline;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientContractController extends Controller
{
    public function show(FinanceRequest $financeRequest): JsonResponse
    {
        $this->authorizeClient($financeRequest);

        $financeRequest->load([
            'client:id,name,email,phone',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments',
            'timeline.actor:id,name',
            'currentContract',
        ]);

        return response()->json([
            'request' => $financeRequest,
            'contract' => $financeRequest->currentContract,
        ]);
    }

    public function sign(SignClientContractRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $this->authorizeClient($financeRequest);
        $client = $request->user();

        $contract = $financeRequest->currentContract;
        abort_unless($contract && $contract->admin_signed_at, 404, 'No contract is ready for client signature.');

        DB::transaction(function () use ($request, $financeRequest, $client, $contract) {
            $signaturePath = $this->storeSignatureDataUrl(
                (string) $request->input('signature_data_url'),
                'contracts/signatures/client/' . $financeRequest->id . '-contract-' . $contract->id . '-client.png'
            );

            $contract->client_signature_path = $signaturePath;
            $contract->client_signed_at = now();
            $contract->client_signed_by = $client->id;
            $contract->status = ContractStatus::FULLY_SIGNED;
            $contract->save();

            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::READY_FOR_PROCESSING;
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimeline::create([
                'finance_request_id' => $financeRequest->id,
                'actor_user_id' => $client->id,
                'event_type' => 'contract.client_signed',
                'event_title' => 'Contract signed by client',
                'event_description' => 'The client reviewed the contract PDF and submitted the final signature.',
                'metadata_json' => [
                    'contract_id' => $contract->id,
                ],
                'created_at' => now(),
            ]);

            if ($contract->contract_pdf_path && Storage::disk('public')->exists($contract->contract_pdf_path)) {
                // Keep existing PDF as audit output for now.
            }
        });

        return response()->json([
            'message' => 'Contract signed successfully.',
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract']),
        ]);
    }

    public function downloadPdf(FinanceRequest $financeRequest)
    {
        $this->authorizeClient($financeRequest);

        $contract = $financeRequest->currentContract;
        abort_unless($contract && $contract->contract_pdf_path && Storage::disk('public')->exists($contract->contract_pdf_path), 404);

        return Storage::disk('public')->download(
            $contract->contract_pdf_path,
            'contract-' . $financeRequest->reference_number . '.pdf'
        );
    }

    private function authorizeClient(FinanceRequest $financeRequest): void
    {
        abort_unless((int) $financeRequest->user_id === (int) auth()->id(), 403);
    }

    private function storeSignatureDataUrl(string $dataUrl, string $relativePath): string
    {
        if (! str_starts_with($dataUrl, 'data:image/')) {
            abort(422, 'Invalid signature payload.');
        }

        [, $encoded] = explode(',', $dataUrl, 2);
        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            abort(422, 'Invalid signature payload.');
        }

        Storage::disk('public')->put($relativePath, $binary);

        return $relativePath;
    }
}
