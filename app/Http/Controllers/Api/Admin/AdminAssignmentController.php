<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignStaffToFinanceRequestRequest;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestStaffAssignment;
use App\Support\RequestTimelineLogger;
use App\Models\User;
use App\Services\FinanceRequestWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAssignmentController extends Controller
{
    public function __construct(
        private readonly FinanceRequestWorkflowService $workflowService,
    ) {
    }

    public function indexReady(Request $request): JsonResponse
    {
        $requests = FinanceRequest::query()
            ->with([
                'client:id,name,email',
                'currentContract:id,finance_request_id,version_no,status,admin_signed_at,client_signed_at',
                'financeRequestType:id,slug,name_en,name_ar',
                'assignments' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('staff:id,name,email')
                    ->orderByDesc('is_primary')
                    ->orderBy('assigned_at'),
            ])
            ->whereHas('currentContract', function ($query) {
                $query->whereIn('status', [
                    ContractStatus::FULLY_SIGNED->value,
                    ContractStatus::CLIENT_SIGNED->value,
                ]);
            })
            ->whereIn('workflow_stage', [
                FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                FinanceRequestWorkflowStage::PROCESSING->value,
                FinanceRequestWorkflowStage::CONTRACT->value,
                FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                FinanceRequestWorkflowStage::ASSIGNED_TO_STAFF->value,
                FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
            ])
            ->orderByRaw(
                "CASE
                    WHEN workflow_stage = ? THEN 0
                    WHEN workflow_stage = ? THEN 1
                    WHEN workflow_stage = ? THEN 2
                    WHEN workflow_stage = ? THEN 3
                    WHEN workflow_stage = ? THEN 4
                    WHEN workflow_stage = ? THEN 5
                    WHEN workflow_stage = ? THEN 6
                    WHEN workflow_stage = ? THEN 7
                    WHEN workflow_stage = ? THEN 8
                    ELSE 9
                END",
                [
                    FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
                    FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
                    FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS->value,
                    FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
                    FinanceRequestWorkflowStage::PROCESSING->value,
                    FinanceRequestWorkflowStage::CONTRACT->value,
                    FinanceRequestWorkflowStage::DOCUMENT_COLLECTION->value,
                    FinanceRequestWorkflowStage::ASSIGNED_TO_STAFF->value,
                    FinanceRequestWorkflowStage::READY_FOR_PROCESSING->value,
                ]
            )
            ->orderByDesc('latest_activity_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'requests' => $requests,
        ]);
    }

    public function staffDirectory(): JsonResponse
    {
        $staff = User::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('account_type', 'staff')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'staff'));
            })
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'permission_names' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                'role_names' => $user->roles->pluck('name')->values()->all(),
            ])
            ->values();

        return response()->json([
            'staff' => $staff,
        ]);
    }

    public function assign(AssignStaffToFinanceRequestRequest $request, FinanceRequest $financeRequest): JsonResponse
    {
        $admin = $request->user();
        $contract = $financeRequest->currentContract;

        abort_unless(
            $contract && in_array($contract->status?->value ?? $contract->status, [ContractStatus::FULLY_SIGNED->value, ContractStatus::CLIENT_SIGNED->value], true),
            422,
            'This request must have a fully signed contract before it can be assigned.'
        );

        $staffIds = collect($request->validated('staff_ids'))->map(fn ($id) => (int) $id)->values();
        $primaryStaffId = $request->filled('primary_staff_id')
            ? (int) $request->input('primary_staff_id')
            : (int) $staffIds->first();
        $notes = $request->filled('notes') ? trim((string) $request->input('notes')) : null;

        DB::transaction(function () use ($financeRequest, $admin, $staffIds, $primaryStaffId, $notes) {
            $existingActiveAssignments = FinanceRequestStaffAssignment::query()
                ->where('finance_request_id', $financeRequest->id)
                ->where('is_active', true)
                ->get()
                ->keyBy('staff_id');

            $staffIdsToKeep = $staffIds->all();
            $removedStaffIds = $existingActiveAssignments->keys()->diff($staffIds)->values();

            if ($removedStaffIds->isNotEmpty()) {
                FinanceRequestStaffAssignment::query()
                    ->where('finance_request_id', $financeRequest->id)
                    ->whereIn('staff_id', $removedStaffIds->all())
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'unassigned_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            foreach ($staffIdsToKeep as $staffId) {
                /** @var FinanceRequestStaffAssignment|null $activeAssignment */
                $activeAssignment = $existingActiveAssignments->get($staffId);
                $isPrimary = $staffId === $primaryStaffId;

                if ($activeAssignment) {
                    $activeAssignment->update([
                        'assignment_role' => $isPrimary ? 'lead' : 'support',
                        'notes' => $notes,
                        'is_primary' => $isPrimary,
                        'assigned_at' => $activeAssignment->assigned_at ?: now(),
                    ]);

                    continue;
                }

                FinanceRequestStaffAssignment::create([
                    'finance_request_id' => $financeRequest->id,
                    'staff_id' => $staffId,
                    'assigned_by' => $admin?->id,
                    'assignment_role' => $isPrimary ? 'lead' : 'support',
                    'notes' => $notes,
                    'is_primary' => $isPrimary,
                    'is_active' => true,
                    'assigned_at' => now(),
                ]);
            }

            $financeRequest->primary_staff_id = $primaryStaffId;
            $financeRequest->latest_assignment_at = now();
            $financeRequest->latest_activity_at = now();
            $financeRequest->save();

            $this->workflowService->moveToDocumentCollection($financeRequest);

            $assignedUsers = User::query()
                ->whereIn('id', $staffIdsToKeep)
                ->orderBy('name')
                ->get(['id', 'name']);

            RequestTimelineLogger::log(
                $financeRequest,
                'request.assigned_to_staff',
                $admin?->id,
                'Request assigned to staff',
                'تم إسناد الطلب إلى الموظفين',
                'The request was assigned to the staff workspace and the client can now upload the required documents.',
                'تم إسناد الطلب إلى مساحة عمل الموظفين ويمكن للعميل الآن رفع المستندات المطلوبة.',
                [
                    'primary_staff_id' => $primaryStaffId,
                    'staff' => $assignedUsers->map(fn (User $user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ])->values()->all(),
                    'notes' => $notes,
                ],
            );
        });

        return response()->json([
            'message' => 'Staff assignment saved successfully.',
            'request' => $financeRequest->fresh([
                'client:id,name,email,phone',
                'answers.question:id,code,question_text,question_type,sort_order',
                'attachments.uploader:id,name',
                'timeline.actor:id,name',
                'comments' => fn ($query) => $query->with('user:id,name,email')->latest(),
                'assignments' => fn ($query) => $query->where('is_active', true)->with('staff:id,name,email')->orderByDesc('is_primary')->orderBy('assigned_at'),
                'currentContract',
                'shareholders',
                'additionalDocuments.requester:id,name',
                'additionalDocuments.uploader:id,name',
                'financeRequestType:id,slug,name_en,name_ar,description_en,description_ar,is_active,sort_order',
            ]),
        ]);
    }
}
