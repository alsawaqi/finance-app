<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SubmitFinanceRequestUpdateFileRequest;
use App\Http\Requests\Client\SubmitFinanceRequestUpdateValueRequest;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestUpdateItem;
use App\Services\FinanceRequestUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClientRequestUpdateController extends Controller
{
    public function __construct(
        private readonly FinanceRequestUpdateService $updateService,
    ) {
    }

    public function submitValue(
        SubmitFinanceRequestUpdateValueRequest $request,
        FinanceRequest $financeRequest,
        FinanceRequestUpdateItem $updateItem,
    ): JsonResponse {
        DB::transaction(function () use ($request, $financeRequest, $updateItem) {
            $this->updateService->submitClientValueUpdate(
                $financeRequest,
                $updateItem,
                $request->user(),
                $request->input('value'),
            );
        });

        return response()->json([
            'message' => 'Update item submitted successfully.',
            'request' => app(ClientRequestController::class)->show($request, $financeRequest->fresh())->getData(true)['request'],
        ]);
    }

    public function submitFile(
        SubmitFinanceRequestUpdateFileRequest $request,
        FinanceRequest $financeRequest,
        FinanceRequestUpdateItem $updateItem,
    ): JsonResponse {
        DB::transaction(function () use ($request, $financeRequest, $updateItem) {
            $this->updateService->submitClientFileUpdate(
                $financeRequest,
                $updateItem,
                $request->user(),
                $request->file('file'),
            );
        });

        return response()->json([
            'message' => 'Update file submitted successfully.',
            'request' => app(ClientRequestController::class)->show($request, $financeRequest->fresh())->getData(true)['request'],
        ]);
    }
}
