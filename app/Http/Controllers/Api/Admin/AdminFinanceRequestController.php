<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveFinanceRequestRequest;
use App\Models\FinanceRequest;
use App\Models\RequestTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFinanceRequestController extends Controller
{
    public function indexNew(Request $request): JsonResponse
    {
        $requests = FinanceRequest::query()
            ->with(['client:id,name,email', 'currentContract:id,finance_request_id,status'])
            ->where('status', FinanceRequestStatus::SUBMITTED)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'requests' => $requests,
        ]);
    }

    public function show(FinanceRequest $financeRequest): JsonResponse
    {
        $financeRequest->load([
            'client:id,name,email,phone',
            'answers.question:id,code,question_text,question_type,sort_order',
            'attachments.uploader:id,name',
            'timeline.actor:id,name',
            'comments' => fn ($query) => $query->with('user:id,name,email')->latest(),
            'assignments' => fn ($query) => $query->where('is_active', true)->with(['staff:id,name,email', 'assignedBy:id,name,email'])->orderByDesc('is_primary')->orderBy('assigned_at'),
            'currentContract',
        ]);

        return response()->json([
            'request' => $financeRequest,
        ]);
    }

    public function approve(ApproveFinanceRequestRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $admin = $request->user();

        DB::transaction(function () use ($financeRequest, $admin, $request) {
            if (blank($financeRequest->approval_reference_number)) {
                $financeRequest->approval_reference_number = 'APR-' . now()->format('Y') . '-' . str_pad((string) $financeRequest->id, 6, '0', STR_PAD_LEFT);
            }

            $financeRequest->status = FinanceRequestStatus::ACTIVE;
            $financeRequest->workflow_stage = FinanceRequestWorkflowStage::CONTRACT;
            $financeRequest->approved_at = $financeRequest->approved_at ?: now();
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            RequestTimeline::create([
                'finance_request_id' => $financeRequest->id,
                'actor_user_id' => $admin?->id,
                'event_type' => 'request.approved',
                'event_title' => 'Request approved for contract creation',
                'event_description' => $request->input('approval_notes') ?: 'The request was reviewed and approved. Contract drafting can now begin.',
                'metadata_json' => [
                    'approval_reference_number' => $financeRequest->approval_reference_number,
                ],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Request approved successfully.',
            'request' => $financeRequest->fresh(['client:id,name,email', 'currentContract']),
        ]);
    }
}
