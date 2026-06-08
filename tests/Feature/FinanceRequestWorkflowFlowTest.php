<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestStaffQuestionStatus;
use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestUnderstudyStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\RequestAdditionalDocumentStatus;
use App\Enums\RequestDocumentUploadStatus;
use App\Enums\RequestEmailDeliveryStatus;
use App\Jobs\SendFinanceRequestEmailJob;
use App\Models\Agent;
use App\Models\Bank;
use App\Models\Contract;
use App\Models\DocumentUploadStep;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestAgentAssignment;
use App\Models\FinanceRequestAgentAssignmentDocument;
use App\Models\FinanceRequestStaffAssignment;
use App\Models\FinanceRequestStaffQuestion;
use App\Models\FinanceStaffQuestionTemplate;
use App\Models\RequestAttachment;
use App\Models\RequestAdditionalDocument;
use App\Models\RequestDocumentUpload;
use App\Models\RequestEmail;
use App\Models\RequestEmailTemplate;
use App\Models\RequestQuestion;
use App\Models\RequestTimeline;
use App\Models\User;
use App\Services\FinanceRequestWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceRequestWorkflowFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('public');
        Notification::fake();

        config([
            'services.twilio.request_submitted_whatsapp_enabled' => false,
            'services.twilio.client_stage_whatsapp_enabled' => false,
        ]);
    }

    public function test_staff_cannot_approve_a_request(): void
    {
        $client = $this->createUser('client');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client);

        $response = $this
            ->actingAs($staff)
            ->postJson("/api/admin/requests/{$financeRequest->id}/approve", [
                'approval_notes' => 'Looks good.',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_manually_override_workflow_stage_to_any_valid_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client);

        $response = $this
            ->actingAs($admin)
            ->patchJson("/api/admin/requests/{$financeRequest->id}/workflow-stage", [
                'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING->value,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING->value,
        ]);
    }

    public function test_invalid_workflow_step_jump_cannot_be_saved_directly(): void
    {
        $client = $this->createUser('client');
        $financeRequest = $this->createFinanceRequest($client);

        $this->expectException(ValidationException::class);

        $financeRequest->forceFill([
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
        ])->save();
    }

    public function test_admin_manual_workflow_stage_override_logs_timeline_without_approval_side_effects(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client);

        $response = $this
            ->actingAs($admin)
            ->patchJson("/api/admin/requests/{$financeRequest->id}/workflow-stage", [
                'workflow_stage' => FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value);

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'status' => FinanceRequestStatus::SUBMITTED->value,
            'workflow_stage' => FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
            'approval_reference_number' => null,
        ]);

        $timeline = RequestTimeline::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('event_type', 'request.workflow_stage_changed')
            ->latest('id')
            ->first();

        $this->assertNotNull($timeline);
        $this->assertSame($admin->id, $timeline->actor_user_id);
        $this->assertSame([
            'from' => FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
            'to' => FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION->value,
        ], $timeline->metadata_json);
    }

    public function test_admin_cannot_approve_request_outside_submitted_review_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000001',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/approve", [
                'approval_notes' => 'Attempted re-approval.',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only newly submitted requests that are still awaiting review can be approved.');

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'status' => FinanceRequestStatus::ACTIVE->value,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
        ]);
    }

    public function test_contract_upload_is_blocked_until_request_reaches_contract_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client);

        $this->withExceptionHandling();

        $response = $this
            ->actingAs($admin)
            ->post(
                "/api/admin/requests/{$financeRequest->id}/contract",
                [
                    'uploaded_contract_file' => UploadedFile::fake()->create('signed-contract.pdf', 64, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This request is not currently in a contract stage that can be updated.');
    }

    public function test_admin_uploaded_contract_branch_moves_to_staff_assignment_and_document_collection_after_staff_assignment(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::ADMIN_CONTRACT_PREPARATION,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000002',
        ]);

        $uploadResponse = $this
            ->actingAs($admin)
            ->post("/api/admin/requests/{$financeRequest->id}/contract", [
                'uploaded_contract_file' => UploadedFile::fake()->create('signed-contract.pdf', 64, 'application/pdf'),
            ]);

        $uploadResponse->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
        ]);

        $assignResponse = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/assign-staff", [
                'staff_ids' => [$staff->id],
                'primary_staff_id' => $staff->id,
                'notes' => 'Primary owner assigned.',
            ]);

        $assignResponse->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'primary_staff_id' => $staff->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
        ]);

        $this->assertDatabaseHas('finance_request_staff_assignments', [
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $staff->id,
            'is_primary' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_reassign_lead_staff_after_document_collection_has_started(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $firstStaff = $this->createUser('staff');
        $replacementStaff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS,
            'primary_staff_id' => $firstStaff->id,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000003',
        ]);

        $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::FULLY_SIGNED,
            'client_signed_at' => now(),
            'client_signed_by' => $client->id,
        ]);

        FinanceRequestStaffAssignment::create([
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $firstStaff->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'lead',
            'is_primary' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/assign-staff", [
                'staff_ids' => [$replacementStaff->id],
                'primary_staff_id' => $replacementStaff->id,
                'notes' => 'New lead takes over the file.',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'primary_staff_id' => $replacementStaff->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
        ]);

        $this->assertDatabaseHas('finance_request_staff_assignments', [
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $firstStaff->id,
            'is_active' => 0,
            'is_primary' => 0,
        ]);

        $this->assertDatabaseHas('finance_request_staff_assignments', [
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $replacementStaff->id,
            'assignment_role' => 'lead',
            'notes' => 'New lead takes over the file.',
            'is_active' => 1,
            'is_primary' => 1,
        ]);

        $timeline = RequestTimeline::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('event_type', 'request.staff_assignment_updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $timeline->actor_user_id);
        $this->assertSame($firstStaff->id, $timeline->metadata_json['previous_primary_staff_id']);
        $this->assertSame($replacementStaff->id, $timeline->metadata_json['primary_staff_id']);
        $this->assertSame([[
            'id' => $firstStaff->id,
            'name' => $firstStaff->name,
        ]], $timeline->metadata_json['removed_staff']);
        $this->assertSame([[
            'id' => $replacementStaff->id,
            'name' => $replacementStaff->name,
            'is_primary' => true,
            'can_request_client_updates' => false,
        ]], $timeline->metadata_json['active_staff']);

        $detailsResponse = $this
            ->actingAs($admin)
            ->getJson("/api/admin/requests/{$financeRequest->id}");

        $detailsResponse
            ->assertOk()
            ->assertJsonCount(1, 'request.assignments')
            ->assertJsonPath('request.assignments.0.staff_id', $replacementStaff->id)
            ->assertJsonPath('request.assignments.0.is_active', true);
    }

    public function test_staff_question_answer_keeps_the_staff_member_who_answered_even_after_reassignment(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $firstStaff = $this->createUser('staff');
        $replacementStaff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS,
            'primary_staff_id' => $firstStaff->id,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000003-A',
        ]);

        $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::FULLY_SIGNED,
            'client_signed_at' => now(),
            'client_signed_by' => $client->id,
        ]);

        FinanceRequestStaffAssignment::create([
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $firstStaff->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'lead',
            'is_primary' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $staffQuestion = FinanceRequestStaffQuestion::create([
            'finance_request_id' => $financeRequest->id,
            'asked_by' => $admin->id,
            'assigned_to' => $firstStaff->id,
            'question_text_en' => 'What did the client provide?',
            'question_type' => 'textarea',
            'is_required' => true,
            'status' => FinanceRequestStaffQuestionStatus::PENDING,
            'asked_at' => now(),
        ]);

        $answerResponse = $this
            ->actingAs($firstStaff)
            ->patchJson("/api/staff/requests/{$financeRequest->id}/staff-questions/{$staffQuestion->id}/answer", [
                'answer_text' => 'The client provided complete income evidence.',
            ]);

        $answerResponse->assertOk();

        $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/assign-staff", [
                'staff_ids' => [$replacementStaff->id],
                'primary_staff_id' => $replacementStaff->id,
                'notes' => 'Replacement staff takes over.',
            ])
            ->assertOk();

        $detailsResponse = $this
            ->actingAs($admin)
            ->getJson("/api/admin/requests/{$financeRequest->id}");

        $detailsResponse
            ->assertOk()
            ->assertJsonPath('request.staff_questions.0.answered_by', $firstStaff->id)
            ->assertJsonPath('request.staff_questions.0.answerer.name', $firstStaff->name)
            ->assertJsonPath('request.staff_questions.0.assigned_to', $firstStaff->id);

        $timeline = RequestTimeline::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('event_type', 'staff_question.answered')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($firstStaff->id, $timeline->actor_user_id);
        $this->assertSame($firstStaff->id, $timeline->metadata_json['answered_by']);
        $this->assertSame($firstStaff->name, $timeline->metadata_json['answered_by_name']);
    }

    public function test_staff_needs_request_assignment_edit_access_to_request_client_updates(): void
    {
        $client = $this->createUser('client');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
        ]);

        $response = $this
            ->actingAs($staff)
            ->postJson("/api/staff/requests/{$financeRequest->id}/update-batches", [
                'reason_en' => 'Please correct the company name.',
                'items' => [[
                    'item_type' => 'intake_field',
                    'field_key' => 'company_name',
                    'label_en' => 'Company name',
                    'instruction_en' => 'Enter the correct legal company name.',
                ]],
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_grant_request_edit_access_when_assigning_staff(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $editableStaff = $this->createUser('staff');
        $readonlyStaff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000EDIT',
        ]);

        $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::FULLY_SIGNED,
            'client_signed_at' => now(),
            'client_signed_by' => $client->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/assign-staff", [
                'staff_ids' => [$editableStaff->id, $readonlyStaff->id],
                'primary_staff_id' => $editableStaff->id,
                'staff_edit_permissions' => [
                    $editableStaff->id => true,
                    $readonlyStaff->id => false,
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('request.assignments.0.can_request_client_updates', true)
            ->assertJsonPath('request.assignments.1.can_request_client_updates', false);

        $this->assertDatabaseHas('finance_request_staff_assignments', [
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $editableStaff->id,
            'can_request_client_updates' => 1,
        ]);

        $this->assertDatabaseHas('finance_request_staff_assignments', [
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $readonlyStaff->id,
            'can_request_client_updates' => 0,
        ]);
    }

    public function test_staff_with_request_assignment_edit_access_can_request_client_updates(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'company_name' => 'Old Company LLC',
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
            'intake_details_json' => [
                'company_name' => 'Old Company LLC',
                'email' => $client->email,
            ],
        ]);

        FinanceRequestStaffAssignment::create([
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $staff->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'lead',
            'is_primary' => true,
            'is_active' => true,
            'can_request_client_updates' => true,
            'assigned_at' => now(),
        ]);

        $question = RequestQuestion::create([
            'code' => 'business_activity',
            'question_text' => 'What is the business activity?',
            'question_type' => 'textarea',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($staff)
            ->postJson("/api/staff/requests/{$financeRequest->id}/update-batches", [
                'reason_en' => 'The client must correct the company information and business activity.',
                'items' => [
                    [
                        'item_type' => 'intake_field',
                        'field_key' => 'company_name',
                        'label_en' => 'Company name',
                        'instruction_en' => 'Enter the correct legal company name.',
                    ],
                    [
                        'item_type' => 'request_answer',
                        'question_id' => $question->id,
                        'label_en' => 'Business activity',
                        'instruction_en' => 'Explain the current business activity again.',
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value);

        $this->assertDatabaseHas('finance_request_update_batches', [
            'finance_request_id' => $financeRequest->id,
            'requested_by' => $staff->id,
            'status' => 'open',
            'return_workflow_stage' => FinanceRequestWorkflowStage::PROCESSING->value,
        ]);

        $this->assertDatabaseHas('finance_request_update_items', [
            'finance_request_id' => $financeRequest->id,
            'requested_by' => $staff->id,
            'item_type' => 'intake_field',
            'field_key' => 'company_name',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('finance_request_update_items', [
            'finance_request_id' => $financeRequest->id,
            'requested_by' => $staff->id,
            'item_type' => 'request_answer',
            'question_id' => $question->id,
            'status' => 'pending',
        ]);
    }

    public function test_staff_can_request_replacement_for_uploaded_additional_document(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_ADDITIONAL_DOCUMENTS,
        ]);
        $this->assignStaffToRequest($financeRequest, $staff, $admin, canRequestClientUpdates: true);

        Storage::disk('public')->put('request-documents/additional/old-bank.pdf', 'old document');

        $additionalDocument = RequestAdditionalDocument::create([
            'finance_request_id' => $financeRequest->id,
            'requested_by' => $staff->id,
            'title' => 'Updated bank statement',
            'reason' => 'Needed for lender review.',
            'status' => RequestAdditionalDocumentStatus::UPLOADED,
            'file_name' => 'old-bank.pdf',
            'file_path' => 'request-documents/additional/old-bank.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 1024,
            'uploaded_by' => $client->id,
            'requested_at' => now()->subDay(),
            'uploaded_at' => now(),
        ]);

        $response = $this
            ->actingAs($staff)
            ->postJson("/api/staff/requests/{$financeRequest->id}/additional-documents/{$additionalDocument->id}/request-change", [
                'reason' => 'The uploaded statement is expired. Please upload a current version.',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('request_additional_documents', [
            'id' => $additionalDocument->id,
            'status' => RequestAdditionalDocumentStatus::REJECTED->value,
            'rejection_reason' => 'The uploaded statement is expired. Please upload a current version.',
            'reviewed_by' => $staff->id,
        ]);

        $clientUploadResponse = $this
            ->actingAs($client)
            ->post(
                "/api/client/requests/{$financeRequest->id}/additional-documents/{$additionalDocument->id}/upload",
                [
                    'file' => UploadedFile::fake()->create('current-bank.pdf', 64, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            );

        $clientUploadResponse->assertOk();

        $this->assertDatabaseHas('request_additional_documents', [
            'id' => $additionalDocument->id,
            'status' => RequestAdditionalDocumentStatus::UPLOADED->value,
            'file_name' => 'current-bank.pdf',
            'uploaded_by' => $client->id,
            'rejection_reason' => null,
        ]);
    }

    public function test_staff_needs_request_assignment_edit_access_to_request_required_document_change(): void
    {
        $client = $this->createUser('client');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
        ]);
        $step = DocumentUploadStep::create([
            'code' => 'BANK',
            'name' => 'Bank statement',
            'is_active' => true,
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $this->createRequiredUpload($financeRequest, $step, $client, 'bank-old.pdf', 'request-documents/required/bank-old.pdf');

        $response = $this
            ->actingAs($staff)
            ->postJson("/api/staff/requests/{$financeRequest->id}/required-documents/{$step->id}/request-change", [
                'reason' => 'The bank statement needs a newer version.',
            ]);

        $response->assertForbidden();
    }

    public function test_staff_with_request_assignment_edit_access_can_replace_required_document_at_any_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
        ]);
        $this->assignStaffToRequest($financeRequest, $staff, $admin, canRequestClientUpdates: true);
        $step = DocumentUploadStep::create([
            'code' => 'BANK',
            'name' => 'Bank statement',
            'is_active' => true,
            'is_required' => true,
            'is_multiple' => false,
            'sort_order' => 1,
        ]);
        $oldUpload = $this->createRequiredUpload($financeRequest, $step, $client, 'bank-old.pdf', 'request-documents/required/bank-old.pdf');

        $response = $this
            ->actingAs($staff)
            ->post(
                "/api/staff/requests/{$financeRequest->id}/required-documents/upload",
                [
                    'document_upload_step_id' => $step->id,
                    'file' => UploadedFile::fake()->create('bank-new.pdf', 64, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            );

        $response
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $this->assertDatabaseHas('request_document_uploads', [
            'id' => $oldUpload->id,
            'status' => RequestDocumentUploadStatus::REJECTED->value,
            'reviewed_by' => $staff->id,
        ]);

        $this->assertDatabaseHas('request_document_uploads', [
            'finance_request_id' => $financeRequest->id,
            'document_upload_step_id' => $step->id,
            'file_name' => 'bank-new.pdf',
            'status' => RequestDocumentUploadStatus::UPLOADED->value,
            'uploaded_by' => $staff->id,
        ]);
    }

    public function test_staff_with_request_assignment_edit_access_can_delete_required_document_upload(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
        ]);
        $this->assignStaffToRequest($financeRequest, $staff, $admin, canRequestClientUpdates: true);
        $step = DocumentUploadStep::create([
            'code' => 'BANK',
            'name' => 'Bank statement',
            'is_active' => true,
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $upload = $this->createRequiredUpload($financeRequest, $step, $client, 'bank-old.pdf', 'request-documents/required/bank-old.pdf');

        $response = $this
            ->actingAs($staff)
            ->deleteJson("/api/staff/requests/{$financeRequest->id}/required-documents/uploads/{$upload->id}");

        $response
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value)
            ->assertJsonPath('required_documents.0.is_uploaded', false);

        $this->assertDatabaseMissing('request_document_uploads', [
            'id' => $upload->id,
        ]);
        Storage::disk('public')->assertMissing('request-documents/required/bank-old.pdf');
    }

    public function test_staff_with_request_assignment_edit_access_can_upload_and_delete_additional_document_file(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
        ]);
        $this->assignStaffToRequest($financeRequest, $staff, $admin, canRequestClientUpdates: true);

        $additionalDocument = RequestAdditionalDocument::create([
            'finance_request_id' => $financeRequest->id,
            'requested_by' => $admin->id,
            'title' => 'Updated bank statement',
            'reason' => 'Needed for lender review.',
            'status' => RequestAdditionalDocumentStatus::PENDING,
            'requested_at' => now(),
        ]);

        $uploadResponse = $this
            ->actingAs($staff)
            ->post(
                "/api/staff/requests/{$financeRequest->id}/additional-documents/{$additionalDocument->id}/upload",
                [
                    'file' => UploadedFile::fake()->create('bank-uploaded.pdf', 64, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            );

        $uploadResponse
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $this->assertDatabaseHas('request_additional_documents', [
            'id' => $additionalDocument->id,
            'status' => RequestAdditionalDocumentStatus::UPLOADED->value,
            'file_name' => 'bank-uploaded.pdf',
            'uploaded_by' => $staff->id,
        ]);

        $deleteResponse = $this
            ->actingAs($staff)
            ->deleteJson("/api/staff/requests/{$financeRequest->id}/additional-documents/{$additionalDocument->id}/file");

        $deleteResponse
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $this->assertDatabaseHas('request_additional_documents', [
            'id' => $additionalDocument->id,
            'status' => RequestAdditionalDocumentStatus::PENDING->value,
            'file_name' => null,
            'file_path' => null,
            'uploaded_by' => null,
        ]);
    }

    public function test_staff_with_request_assignment_edit_access_can_request_additional_document_at_any_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
        ]);
        $this->assignStaffToRequest($financeRequest, $staff, $admin, canRequestClientUpdates: true);

        $response = $this
            ->actingAs($staff)
            ->postJson("/api/staff/requests/{$financeRequest->id}/additional-documents", [
                'title' => 'Updated bank statement',
                'reason' => 'The bank needs a more recent statement.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::CLIENT_UPDATE_REQUESTED->value);

        $this->assertDatabaseHas('request_additional_documents', [
            'finance_request_id' => $financeRequest->id,
            'requested_by' => $staff->id,
            'title' => 'Updated bank statement',
            'status' => RequestAdditionalDocumentStatus::PENDING->value,
        ]);
    }

    public function test_staff_cannot_submit_understudy_before_request_reaches_understudy_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS,
        ]);
        $this->assignStaffToRequest($financeRequest, $staff, $admin);

        $response = $this
            ->actingAs($staff)
            ->postJson("/api/staff/requests/{$financeRequest->id}/understudy-submit", [
                'understudy_note' => 'Trying to submit before the document stage is complete.',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('workflow_stage');

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
            'understudy_status' => FinanceRequestUnderstudyStatus::DRAFT->value,
        ]);
    }

    public function test_staff_cannot_answer_understudy_question_before_request_reaches_understudy_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS,
        ]);
        $this->assignStaffToRequest($financeRequest, $staff, $admin);

        $staffQuestion = FinanceRequestStaffQuestion::create([
            'finance_request_id' => $financeRequest->id,
            'question_code' => 'early-study',
            'question_text_en' => 'Can the request be studied?',
            'question_type' => 'text',
            'status' => FinanceRequestStaffQuestionStatus::PENDING,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($staff)
            ->patchJson("/api/staff/requests/{$financeRequest->id}/staff-questions/{$staffQuestion->id}/answer", [
                'answer_text' => 'Trying to answer before document collection is complete.',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('workflow_stage');

        $this->assertDatabaseHas('finance_request_staff_questions', [
            'id' => $staffQuestion->id,
            'status' => FinanceRequestStaffQuestionStatus::PENDING->value,
            'answer_text' => null,
        ]);
    }

    public function test_admin_cannot_advance_understudy_before_request_reaches_understudy_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS,
            'understudy_status' => FinanceRequestUnderstudyStatus::DRAFT,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/advance-understudy", [
                'review_note' => 'Trying to skip document collection and staff study.',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('workflow_stage');

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS->value,
            'understudy_status' => FinanceRequestUnderstudyStatus::DRAFT->value,
        ]);
    }

    public function test_admin_can_directly_update_request_information_answers_and_files_without_client_batch(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
            'company_name' => 'Old Company',
            'intake_details_json' => [
                'finance_type' => 'individual',
                'company_name' => 'Old Company',
                'requested_amount' => 50000,
                'email' => 'old@example.com',
            ],
        ]);

        $question = RequestQuestion::create([
            'code' => 'RQ-DIRECT',
            'question_text' => 'What is the purpose?',
            'question_type' => 'textarea',
            'finance_type' => 'all',
            'is_required' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patchJson("/api/admin/requests/{$financeRequest->id}/direct-edit", [
                'intake_details' => [
                    'finance_type' => 'company',
                    'company_name' => 'Corrected Company LLC',
                    'requested_amount' => 125000,
                    'email' => 'corrected@example.com',
                ],
                'answers' => [
                    [
                        'question_id' => $question->id,
                        'value' => 'Working capital expansion.',
                    ],
                ],
                'note' => 'Admin corrected the request directly.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('request.company_name', 'Corrected Company LLC')
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'applicant_type' => 'company',
            'company_name' => 'Corrected Company LLC',
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING->value,
        ]);
        $this->assertSame('corrected@example.com', $financeRequest->fresh()->intake_details_json['email']);
        $this->assertDatabaseHas('request_answers', [
            'finance_request_id' => $financeRequest->id,
            'question_id' => $question->id,
            'answer_text' => 'Working capital expansion.',
            'answered_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('finance_request_update_batches', 0);

        $uploadResponse = $this
            ->actingAs($admin)
            ->post(
                "/api/admin/requests/{$financeRequest->id}/attachments",
                [
                    'category' => 'initial_submission',
                    'file' => UploadedFile::fake()->create('corrected-request.pdf', 64, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            );

        $uploadResponse
            ->assertCreated()
            ->assertJsonPath('attachment.file_name', 'corrected-request.pdf');

        $attachment = RequestAttachment::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('category', 'initial_submission')
            ->firstOrFail();

        Storage::disk('public')->assertExists($attachment->file_path);

        $deleteResponse = $this
            ->actingAs($admin)
            ->deleteJson("/api/admin/requests/{$financeRequest->id}/attachments/{$attachment->id}");

        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('request_attachments', [
            'id' => $attachment->id,
        ]);
        Storage::disk('public')->assertMissing($attachment->file_path);

        $requiredStep = DocumentUploadStep::create([
            'code' => 'bank-statement',
            'name' => 'Bank statement',
            'description' => 'Latest statement',
            'is_required' => true,
            'is_multiple' => false,
            'allowed_file_types_json' => ['pdf'],
            'max_file_size_mb' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $requiredUploadResponse = $this
            ->actingAs($admin)
            ->post(
                "/api/admin/requests/{$financeRequest->id}/required-documents/upload",
                [
                    'document_upload_step_id' => $requiredStep->id,
                    'file' => UploadedFile::fake()->create('admin-bank-statement.pdf', 64, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            );

        $requiredUploadResponse
            ->assertCreated()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $requiredUpload = RequestDocumentUpload::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('document_upload_step_id', $requiredStep->id)
            ->firstOrFail();

        Storage::disk('public')->assertExists($requiredUpload->file_path);

        $deleteRequiredResponse = $this
            ->actingAs($admin)
            ->deleteJson("/api/admin/requests/{$financeRequest->id}/required-documents/uploads/{$requiredUpload->id}");

        $deleteRequiredResponse
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $this->assertDatabaseMissing('request_document_uploads', [
            'id' => $requiredUpload->id,
        ]);
        Storage::disk('public')->assertMissing($requiredUpload->file_path);

        $additionalDocument = RequestAdditionalDocument::create([
            'finance_request_id' => $financeRequest->id,
            'requested_by' => $admin->id,
            'title' => 'Updated financial model',
            'status' => RequestAdditionalDocumentStatus::PENDING->value,
            'requested_at' => now(),
        ]);

        $additionalUploadResponse = $this
            ->actingAs($admin)
            ->post(
                "/api/admin/requests/{$financeRequest->id}/additional-documents/{$additionalDocument->id}/upload",
                [
                    'file' => UploadedFile::fake()->create('updated-model.pdf', 64, 'application/pdf'),
                ],
                ['Accept' => 'application/json']
            );

        $additionalUploadResponse
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $additionalDocument->refresh();
        $this->assertSame(RequestAdditionalDocumentStatus::UPLOADED, $additionalDocument->status);
        Storage::disk('public')->assertExists($additionalDocument->file_path);

        $deleteAdditionalResponse = $this
            ->actingAs($admin)
            ->deleteJson("/api/admin/requests/{$financeRequest->id}/additional-documents/{$additionalDocument->id}/file");

        $deleteAdditionalResponse
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $this->assertDatabaseHas('request_additional_documents', [
            'id' => $additionalDocument->id,
            'status' => RequestAdditionalDocumentStatus::PENDING->value,
            'file_name' => null,
            'file_path' => null,
        ]);
        Storage::disk('public')->assertMissing($additionalDocument->file_path);
    }

    public function test_generated_contract_without_commercial_registration_moves_to_staff_assignment_after_client_signature(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000004',
        ]);

        $contract = $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::ADMIN_SIGNED,
            'requires_commercial_registration' => false,
        ]);

        $response = $this
            ->actingAs($client)
            ->postJson("/api/client/requests/{$financeRequest->id}/contract/sign", [
                'signature_data_url' => $this->signatureDataUrl(),
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
        ]);

        $contract->refresh();

        $this->assertSame(ContractStatus::FULLY_SIGNED, $contract->status);
        $this->assertSame($client->id, $contract->client_signed_by);
        $this->assertNotNull($contract->client_signed_at);
        $this->assertNotNull($contract->client_signature_path);
    }

    public function test_generated_contract_with_commercial_registration_stops_for_client_upload_after_signature(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_SIGNATURE,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000005',
        ]);

        $contract = $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::ADMIN_SIGNED,
            'requires_commercial_registration' => true,
        ]);

        $response = $this
            ->actingAs($client)
            ->postJson("/api/client/requests/{$financeRequest->id}/contract/sign", [
                'signature_data_url' => $this->signatureDataUrl(),
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD->value,
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'requires_commercial_registration' => 1,
            'status' => ContractStatus::FULLY_SIGNED->value,
            'client_signed_by' => $client->id,
        ]);
    }

    public function test_commercial_registration_branch_moves_from_client_upload_to_admin_upload_then_staff_assignment(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000006',
        ]);

        $contract = $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::FULLY_SIGNED,
            'requires_commercial_registration' => true,
            'client_signed_at' => now(),
            'client_signature_path' => 'contracts/signatures/client-signature.png',
        ]);

        $clientUpload = $this
            ->actingAs($client)
            ->post("/api/client/requests/{$financeRequest->id}/contract/commercial-registration", [
                'file' => UploadedFile::fake()->create('client-commercial.pdf', 64, 'application/pdf'),
            ]);

        $clientUpload->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_ADMIN_COMMERCIAL_REGISTRATION_UPLOAD->value,
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'finance_request_id' => $financeRequest->id,
        ]);

        $adminUpload = $this
            ->actingAs($admin)
            ->post("/api/admin/requests/{$financeRequest->id}/contract/commercial-registration/admin-upload", [
                'file' => UploadedFile::fake()->create('admin-commercial.pdf', 64, 'application/pdf'),
            ]);

        $adminUpload->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ASSIGNMENT->value,
        ]);
    }

    public function test_staff_study_question_templates_are_filtered_by_request_finance_type(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $financeRequest = $this->createFinanceRequest($client, [
            'applicant_type' => 'company',
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS,
            'primary_staff_id' => $staff->id,
            'intake_details_json' => [
                'finance_type' => 'company',
                'email' => $client->email,
            ],
        ]);

        FinanceStaffQuestionTemplate::create([
            'code' => 'FSQ-ALL',
            'question_text_en' => 'Explain the request background.',
            'question_type' => 'textarea',
            'finance_type' => 'all',
            'is_required' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        FinanceStaffQuestionTemplate::create([
            'code' => 'FSQ-COMPANY',
            'question_text_en' => 'Review the company ownership structure.',
            'question_type' => 'textarea',
            'finance_type' => 'company',
            'is_required' => true,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        FinanceStaffQuestionTemplate::create([
            'code' => 'FSQ-INDIVIDUAL',
            'question_text_en' => 'Review the personal income profile.',
            'question_type' => 'textarea',
            'finance_type' => 'individual',
            'is_required' => true,
            'sort_order' => 3,
            'is_active' => true,
        ]);

        app(FinanceRequestWorkflowService::class)->moveToUnderstudy($financeRequest, $admin->id);

        $createdCodes = FinanceRequestStaffQuestion::query()
            ->where('finance_request_id', $financeRequest->id)
            ->orderBy('sort_order')
            ->pluck('question_code')
            ->all();

        $this->assertSame(['FSQ-ALL', 'FSQ-COMPANY'], $createdCodes);
        $this->assertDatabaseMissing('finance_request_staff_questions', [
            'finance_request_id' => $financeRequest->id,
            'question_code' => 'FSQ-INDIVIDUAL',
        ]);
    }

    public function test_admin_can_create_staff_study_question_template_for_specific_finance_type(): void
    {
        $admin = $this->createUser('admin');

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/staff-question-templates', [
                'code' => 'FSQ-COMPANY-CASHFLOW',
                'question_text_en' => 'Summarize the company cashflow.',
                'question_text_ar' => 'لخص التدفق النقدي للشركة.',
                'question_type' => 'textarea',
                'finance_type' => 'company',
                'is_required' => true,
                'sort_order' => 4,
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.finance_type', 'company');

        $this->assertDatabaseHas('finance_staff_question_templates', [
            'code' => 'FSQ-COMPANY-CASHFLOW',
            'finance_type' => 'company',
        ]);
    }

    public function test_understudy_approval_accepts_answered_questions_without_individual_question_closure(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW,
            'understudy_status' => FinanceRequestUnderstudyStatus::SUBMITTED,
            'understudy_submitted_at' => now(),
        ]);

        FinanceRequestStaffQuestion::create([
            'finance_request_id' => $financeRequest->id,
            'question_code' => 'study-risk',
            'question_text_en' => 'Has the risk been assessed?',
            'question_type' => 'text',
            'answer_text' => 'Yes, initial assessment completed.',
            'status' => FinanceRequestStaffQuestionStatus::ANSWERED,
            'is_required' => true,
            'sort_order' => 1,
            'answered_at' => now(),
        ]);

        $summaryResponse = $this
            ->actingAs($admin)
            ->getJson("/api/admin/requests/{$financeRequest->id}");

        $summaryResponse
            ->assertOk()
            ->assertJsonPath('staff_question_summary.required_count', 1)
            ->assertJsonPath('staff_question_summary.required_answered_count', 1)
            ->assertJsonPath('staff_question_summary.required_reviewed_count', 0)
            ->assertJsonPath('staff_question_summary.all_required_answered', true)
            ->assertJsonPath('staff_question_summary.can_advance_from_understudy', true);

        $approveResponse = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/understudy-review", [
                'action' => 'approve',
                'review_note' => 'The study answers are acceptable.',
            ]);

        $approveResponse->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'understudy_status' => FinanceRequestUnderstudyStatus::APPROVED->value,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value,
            'understudy_review_note' => 'The study answers are acceptable.',
        ]);

        $this->assertDatabaseHas('finance_request_staff_questions', [
            'finance_request_id' => $financeRequest->id,
            'question_code' => 'study-risk',
            'status' => FinanceRequestStaffQuestionStatus::CLOSED->value,
        ]);

        $revisionRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW,
            'understudy_status' => FinanceRequestUnderstudyStatus::SUBMITTED,
            'understudy_submitted_at' => now(),
        ]);

        FinanceRequestStaffQuestion::create([
            'finance_request_id' => $revisionRequest->id,
            'question_code' => 'study-income',
            'question_text_en' => 'Was income verified?',
            'question_type' => 'text',
            'answer_text' => 'Needs more details.',
            'status' => FinanceRequestStaffQuestionStatus::ANSWERED,
            'is_required' => true,
            'sort_order' => 1,
            'answered_at' => now(),
        ]);

        $rejectResponse = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$revisionRequest->id}/understudy-review", [
                'action' => 'reject',
                'review_note' => 'Please refine the answer.',
            ]);

        $rejectResponse->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $revisionRequest->id,
            'understudy_status' => FinanceRequestUnderstudyStatus::REJECTED->value,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
        ]);
    }

    public function test_admin_cannot_mark_individual_understudy_question_reviewed(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW,
            'understudy_status' => FinanceRequestUnderstudyStatus::SUBMITTED,
            'understudy_submitted_at' => now(),
        ]);

        $staffQuestion = FinanceRequestStaffQuestion::create([
            'finance_request_id' => $financeRequest->id,
            'question_code' => 'study-risk',
            'question_text_en' => 'Has the risk been assessed?',
            'question_type' => 'text',
            'answer_text' => 'Yes, initial assessment completed.',
            'status' => FinanceRequestStaffQuestionStatus::ANSWERED,
            'is_required' => true,
            'sort_order' => 1,
            'answered_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->patchJson("/api/admin/requests/{$financeRequest->id}/staff-questions/{$staffQuestion->id}/review", [
                'action' => 'close',
                'review_note' => 'Looks fine.',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('action');

        $this->assertDatabaseHas('finance_request_staff_questions', [
            'id' => $staffQuestion->id,
            'status' => FinanceRequestStaffQuestionStatus::ANSWERED->value,
            'closed_at' => null,
        ]);
    }

    public function test_admin_can_request_revision_for_specific_understudy_question(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_UNDERSTUDY_REVIEW,
            'understudy_status' => FinanceRequestUnderstudyStatus::SUBMITTED,
            'understudy_submitted_at' => now(),
        ]);

        $staffQuestion = FinanceRequestStaffQuestion::create([
            'finance_request_id' => $financeRequest->id,
            'question_code' => 'study-cashflow',
            'question_text_en' => 'Is cashflow sufficient?',
            'question_type' => 'text',
            'answer_text' => 'Mostly sufficient.',
            'status' => FinanceRequestStaffQuestionStatus::ANSWERED,
            'is_required' => true,
            'sort_order' => 1,
            'answered_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->patchJson("/api/admin/requests/{$financeRequest->id}/staff-questions/{$staffQuestion->id}/review", [
                'action' => 'reopen',
                'review_note' => 'Please add the projected cashflow source.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('request.understudy_status', FinanceRequestUnderstudyStatus::REJECTED->value)
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value)
            ->assertJsonPath('staff_question.status', FinanceRequestStaffQuestionStatus::PENDING->value);

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'understudy_status' => FinanceRequestUnderstudyStatus::REJECTED->value,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
            'understudy_review_note' => 'Please add the projected cashflow source.',
        ]);

        $this->assertDatabaseHas('finance_request_staff_questions', [
            'id' => $staffQuestion->id,
            'status' => FinanceRequestStaffQuestionStatus::PENDING->value,
            'closed_at' => null,
        ]);
    }

    public function test_agent_assignment_options_use_final_contract_asset_and_keep_multiple_required_uploads(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000007',
        ]);

        $contract = $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::FULLY_SIGNED,
            'contract_pdf_path' => 'contracts/pdfs/base-contract.pdf',
            'client_commercial_contract_name' => 'client-commercial.pdf',
            'client_commercial_contract_path' => 'contracts/commercial/client-commercial.pdf',
            'client_commercial_contract_mime_type' => 'application/pdf',
            'client_commercial_uploaded_at' => now(),
            'admin_commercial_contract_name' => 'admin-commercial.pdf',
            'admin_commercial_contract_path' => 'contracts/commercial/admin-commercial.pdf',
            'admin_commercial_contract_mime_type' => 'application/pdf',
            'admin_commercial_uploaded_at' => now(),
            'requires_commercial_registration' => true,
        ]);

        $multiStep = DocumentUploadStep::create([
            'code' => 'bank_statements',
            'name' => 'Bank statements',
            'finance_type' => 'all',
            'is_required' => true,
            'is_multiple' => true,
            'allowed_file_types_json' => ['pdf'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $singleStep = DocumentUploadStep::create([
            'code' => 'passport_copy',
            'name' => 'Passport copy',
            'finance_type' => 'all',
            'is_required' => true,
            'is_multiple' => false,
            'allowed_file_types_json' => ['pdf'],
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->createRequiredUpload($financeRequest, $multiStep, $client, 'statement-1.pdf', 'request-documents/required/statement-1.pdf');
        $this->createRequiredUpload($financeRequest, $multiStep, $client, 'statement-2.pdf', 'request-documents/required/statement-2.pdf');
        $this->createRequiredUpload($financeRequest, $singleStep, $client, 'passport-old.pdf', 'request-documents/required/passport-old.pdf');
        $latestSingleUpload = $this->createRequiredUpload($financeRequest, $singleStep, $client, 'passport-new.pdf', 'request-documents/required/passport-new.pdf');

        $response = $this
            ->actingAs($admin)
            ->getJson("/api/admin/requests/{$financeRequest->id}/agent-assignment-options");

        $response->assertOk();

        $availableDocuments = collect($response->json('available_documents'));
        $requiredDocuments = $availableDocuments->where('document_type', 'required_document')->values();
        $contractDocument = $availableDocuments->firstWhere('document_type', 'contract_pdf');

        $this->assertNotNull($contractDocument);
        $this->assertSame('contracts/commercial/admin-commercial.pdf', $contractDocument['file_path']);
        $this->assertSame('admin-commercial.pdf', $contractDocument['file_name']);

        $this->assertCount(3, $requiredDocuments);
        $this->assertTrue($requiredDocuments->contains(fn (array $document) => $document['file_path'] === 'request-documents/required/statement-1.pdf'));
        $this->assertTrue($requiredDocuments->contains(fn (array $document) => $document['file_path'] === 'request-documents/required/statement-2.pdf'));
        $this->assertTrue($requiredDocuments->contains(fn (array $document) => (int) $document['document_id'] === $latestSingleUpload->id));
        $this->assertFalse($requiredDocuments->contains(fn (array $document) => $document['file_path'] === 'request-documents/required/passport-old.pdf'));

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'admin_commercial_contract_path' => 'contracts/commercial/admin-commercial.pdf',
        ]);
    }

    public function test_admin_can_replace_agent_assignments_while_request_is_processing(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000010',
        ]);

        $contract = $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::FULLY_SIGNED,
        ]);

        $step = DocumentUploadStep::create([
            'code' => 'bank_pack',
            'name' => 'Bank pack',
            'finance_type' => 'all',
            'is_required' => true,
            'is_multiple' => false,
            'allowed_file_types_json' => ['pdf'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $requiredUpload = $this->createRequiredUpload($financeRequest, $step, $client, 'bank-pack.pdf', 'request-documents/required/bank-pack.pdf');

        $bank = Bank::create([
            'name' => 'Alpha Bank',
            'short_name' => 'Alpha',
            'code' => 'ALP',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $oldAgent = Agent::create([
            'name' => 'Old Agent',
            'email' => 'old-agent@example.com',
            'bank_id' => $bank->id,
            'agent_type' => 'bank',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $firstAgent = Agent::create([
            'name' => 'First Agent',
            'email' => 'first-agent@example.com',
            'bank_id' => $bank->id,
            'agent_type' => 'bank',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $secondAgent = Agent::create([
            'name' => 'Second Agent',
            'email' => 'second-agent@example.com',
            'bank_id' => $bank->id,
            'agent_type' => 'bank',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $oldAssignment = FinanceRequestAgentAssignment::create([
            'finance_request_id' => $financeRequest->id,
            'agent_id' => $oldAgent->id,
            'bank_id' => $bank->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        FinanceRequestAgentAssignmentDocument::create([
            'finance_request_agent_assignment_id' => $oldAssignment->id,
            'finance_request_id' => $financeRequest->id,
            'document_type' => 'contract_pdf',
            'document_id' => $contract->id,
            'document_key' => "contract_pdf:{$contract->id}",
            'group_label' => 'Contracts',
            'document_label' => 'Signed contract',
            'file_name' => "request-{$financeRequest->id}.pdf",
            'file_path' => $contract->contract_pdf_path,
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 1024,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/agent-assignments", [
                'review_note' => 'Refresh active bank access.',
                'assignments' => [
                    [
                        'agent_id' => $firstAgent->id,
                        'document_keys' => ["contract_pdf:{$contract->id}"],
                    ],
                    [
                        'agent_id' => $secondAgent->id,
                        'document_keys' => [
                            "contract_pdf:{$contract->id}",
                            "required_document:{$requiredUpload->id}",
                        ],
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::PROCESSING->value);

        $this->assertDatabaseHas('finance_request_agent_assignments', [
            'id' => $oldAssignment->id,
            'is_active' => false,
        ]);

        $this->assertSame(2, FinanceRequestAgentAssignment::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('is_active', true)
            ->count());

        $this->assertDatabaseHas('finance_request_agent_assignment_documents', [
            'finance_request_id' => $financeRequest->id,
            'document_key' => "required_document:{$requiredUpload->id}",
            'file_name' => 'bank-pack.pdf',
        ]);
    }

    public function test_admin_can_clear_agent_assignments_and_return_to_agent_assignment_stage(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000011',
        ]);

        $contract = $this->createContract($financeRequest, $admin, [
            'status' => ContractStatus::FULLY_SIGNED,
        ]);

        $bank = Bank::create([
            'name' => 'Beta Bank',
            'short_name' => 'Beta',
            'code' => 'BET',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $agent = Agent::create([
            'name' => 'Assigned Agent',
            'email' => 'assigned-agent@example.com',
            'bank_id' => $bank->id,
            'agent_type' => 'bank',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $assignment = FinanceRequestAgentAssignment::create([
            'finance_request_id' => $financeRequest->id,
            'agent_id' => $agent->id,
            'bank_id' => $bank->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        FinanceRequestAgentAssignmentDocument::create([
            'finance_request_agent_assignment_id' => $assignment->id,
            'finance_request_id' => $financeRequest->id,
            'document_type' => 'contract_pdf',
            'document_id' => $contract->id,
            'document_key' => "contract_pdf:{$contract->id}",
            'group_label' => 'Contracts',
            'document_label' => 'Signed contract',
            'file_name' => "request-{$financeRequest->id}.pdf",
            'file_path' => $contract->contract_pdf_path,
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 1024,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/agent-assignments", [
                'review_note' => 'Clear bank access.',
                'assignments' => [],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::AWAITING_AGENT_ASSIGNMENT->value);

        $this->assertDatabaseHas('finance_request_agent_assignments', [
            'id' => $assignment->id,
            'is_active' => false,
        ]);

        $this->assertSame(0, FinanceRequestAgentAssignment::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('is_active', true)
            ->count());
    }

    public function test_final_approval_uploads_bank_attachment_for_client_access(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000012',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post("/api/admin/requests/{$financeRequest->id}/final-approve", [
                'final_approval_notes' => 'Approved by the bank.',
                'final_approval_attachments' => [
                    UploadedFile::fake()->create('bank-final-approval.pdf', 80, 'application/pdf'),
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('request.status', FinanceRequestStatus::COMPLETED->value)
            ->assertJsonPath('request.workflow_stage', FinanceRequestWorkflowStage::COMPLETED->value);

        $this->assertDatabaseHas('request_attachments', [
            'finance_request_id' => $financeRequest->id,
            'category' => 'final_approval',
            'file_name' => 'bank-final-approval.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $admin->id,
        ]);

        $attachment = RequestAttachment::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('category', 'final_approval')
            ->firstOrFail();

        Storage::disk('public')->assertExists($attachment->file_path);

        $clientResponse = $this
            ->actingAs($client)
            ->getJson("/api/client/requests/{$financeRequest->id}");

        $clientResponse
            ->assertOk()
            ->assertJsonFragment([
                'id' => $attachment->id,
                'category' => 'final_approval',
                'file_name' => 'bank-final-approval.pdf',
                'download_url' => "/api/client/requests/{$financeRequest->id}/attachments/{$attachment->id}/download",
            ]);

        $this
            ->actingAs($client)
            ->get("/api/client/requests/{$financeRequest->id}/attachments/{$attachment->id}/download")
            ->assertOk();
    }

    public function test_client_required_document_upload_uses_step_allowed_file_types(): void
    {
        $client = $this->createUser('client');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_DOCUMENTS,
        ]);

        $step = DocumentUploadStep::create([
            'code' => 'bank_statement_excel',
            'name' => 'Bank statement Excel',
            'finance_type' => 'all',
            'is_required' => true,
            'is_multiple' => false,
            'allowed_file_types_json' => ['xlsx'],
            'max_file_size_mb' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($client)
            ->post("/api/client/requests/{$financeRequest->id}/documents", [
                'document_upload_step_id' => $step->id,
                'file' => UploadedFile::fake()->create(
                    'bank-statement.xlsx',
                    64,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ),
            ], ['Accept' => 'application/json']);

        $response->assertOk();

        $this->assertDatabaseHas('request_document_uploads', [
            'finance_request_id' => $financeRequest->id,
            'document_upload_step_id' => $step->id,
            'file_name' => 'bank-statement.xlsx',
            'file_extension' => 'xlsx',
            'uploaded_by' => $client->id,
        ]);
    }

    public function test_send_request_email_is_queued_after_response_with_selected_attachments(): void
    {
        Bus::fake();

        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $this->makeMailboxReady($admin);

        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000008',
        ]);

        $bank = Bank::create([
            'name' => 'Alpha Bank',
            'short_name' => 'Alpha',
            'code' => 'ALP',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $agent = Agent::create([
            'name' => 'Agent Smith',
            'email' => 'agent@example.com',
            'bank_id' => $bank->id,
            'agent_type' => 'bank',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $assignment = FinanceRequestAgentAssignment::create([
            'finance_request_id' => $financeRequest->id,
            'agent_id' => $agent->id,
            'bank_id' => $bank->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        Storage::disk('public')->put('request-documents/allowed/agent-pack.pdf', 'document');

        FinanceRequestAgentAssignmentDocument::create([
            'finance_request_agent_assignment_id' => $assignment->id,
            'finance_request_id' => $financeRequest->id,
            'document_type' => 'required_document',
            'document_id' => 101,
            'document_key' => 'required_document:101',
            'group_label' => 'Required documents',
            'document_label' => 'Agent pack',
            'file_name' => 'agent-pack.pdf',
            'file_path' => 'request-documents/allowed/agent-pack.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 1024,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson("/api/staff/requests/{$financeRequest->id}/send-email", [
                'bank_id' => $bank->id,
                'agent_id' => $agent->id,
                'document_keys' => ['required_document:101'],
                'subject' => 'Required package',
                'body' => '<p>Please review the attached file.</p>',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Request email queued successfully. Delivery will continue in the background.')
            ->assertJsonPath('email.delivery_status', RequestEmailDeliveryStatus::QUEUED->value)
            ->assertJsonPath('email.attachments.0.file_name', 'agent-pack.pdf');

        $requestEmail = RequestEmail::query()->firstOrFail();

        $this->assertDatabaseHas('request_emails', [
            'id' => $requestEmail->id,
            'finance_request_id' => $financeRequest->id,
            'delivery_status' => RequestEmailDeliveryStatus::QUEUED->value,
            'subject' => 'Required package',
        ]);

        $this->assertDatabaseHas('request_email_attachments', [
            'request_email_id' => $requestEmail->id,
            'file_name' => 'agent-pack.pdf',
            'file_path' => 'request-documents/allowed/agent-pack.pdf',
        ]);

        $queuedTimeline = RequestTimeline::query()
            ->where('finance_request_id', $financeRequest->id)
            ->where('event_type', 'request.email_queued')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $queuedTimeline->actor_user_id);
        $this->assertSame($requestEmail->id, $queuedTimeline->metadata_json['request_email_id']);
        $this->assertSame($agent->id, $queuedTimeline->metadata_json['agent_id']);
        $this->assertSame('Required package', $queuedTimeline->metadata_json['subject']);

        Bus::assertDispatchedAfterResponse(SendFinanceRequestEmailJob::class, function (SendFinanceRequestEmailJob $job) use ($requestEmail) {
            return $job->requestEmailId === $requestEmail->id;
        });
    }

    public function test_admin_can_create_request_email_template_with_editable_fields(): void
    {
        $admin = $this->createUser('admin');

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/request-email-templates', [
                'name' => 'Welcome template',
                'code' => 'welcome-template',
                'subject' => 'Welcome {{contact_name}}',
                'body' => '<p>Welcome {{contact_name}},</p><p>{{personal_note}}</p>',
                'fields_json' => [
                    [
                        'key' => 'contact_name',
                        'label' => 'Contact name',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'Enter the contact name',
                    ],
                    [
                        'key' => 'personal_note',
                        'label' => 'Personal note',
                        'type' => 'textarea',
                        'required' => false,
                        'placeholder' => 'Optional note',
                    ],
                ],
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Welcome template')
            ->assertJsonPath('data.code', 'welcome-template')
            ->assertJsonPath('data.fields_json.0.key', 'contact_name')
            ->assertJsonPath('data.fields_json.0.required', true)
            ->assertJsonPath('data.fields_json.1.type', 'textarea');

        $this->assertDatabaseHas('request_email_templates', [
            'name' => 'Welcome template',
            'code' => 'welcome-template',
            'subject' => 'Welcome {{contact_name}}',
            'created_by' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_save_request_email_template_with_undefined_placeholder(): void
    {
        $admin = $this->createUser('admin');

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/request-email-templates', [
                'name' => 'Incomplete template',
                'subject' => 'Welcome {{contact_name}}',
                'body' => '<p>Please review {{missing_note}}</p>',
                'fields_json' => [
                    [
                        'key' => 'contact_name',
                        'label' => 'Contact name',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('fields_json');
    }

    public function test_staff_can_send_request_email_from_template_values(): void
    {
        Bus::fake();

        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');
        $this->makeMailboxReady($staff);

        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
            'approval_reference_number' => 'APR-2026-000123',
        ]);

        $bank = Bank::create([
            'name' => 'Template Bank',
            'short_name' => 'TB',
            'code' => 'TB',
            'is_active' => true,
        ]);

        $agent = Agent::create([
            'name' => 'Agent One',
            'email' => 'agent-template@example.com',
            'bank_id' => $bank->id,
            'agent_type' => 'bank',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $assignment = FinanceRequestAgentAssignment::create([
            'finance_request_id' => $financeRequest->id,
            'agent_id' => $agent->id,
            'bank_id' => $bank->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        Storage::disk('public')->put('request-documents/allowed/template-pack.pdf', 'document');

        FinanceRequestAgentAssignmentDocument::create([
            'finance_request_agent_assignment_id' => $assignment->id,
            'finance_request_id' => $financeRequest->id,
            'document_type' => 'required_document',
            'document_id' => 202,
            'document_key' => 'required_document:202',
            'group_label' => 'Required documents',
            'document_label' => 'Template pack',
            'file_name' => 'template-pack.pdf',
            'file_path' => 'request-documents/allowed/template-pack.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 1024,
            'sort_order' => 1,
        ]);

        $template = RequestEmailTemplate::create([
            'name' => 'Welcome template',
            'code' => 'welcome-template',
            'subject' => 'Welcome {{contact_name}}',
            'body' => '<p>Dear {{contact_name}},</p><p>{{personal_note}}</p>',
            'fields_json' => [
                [
                    'key' => 'contact_name',
                    'label' => 'Contact name',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Enter the contact name',
                ],
                [
                    'key' => 'personal_note',
                    'label' => 'Personal note',
                    'type' => 'textarea',
                    'required' => true,
                    'placeholder' => 'Enter the note',
                ],
            ],
            'created_by' => $admin->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($staff)
            ->postJson("/api/staff/requests/{$financeRequest->id}/send-email", [
                'bank_id' => $bank->id,
                'agent_id' => $agent->id,
                'document_keys' => ['required_document:202'],
                'email_template_id' => $template->id,
                'template_values' => [
                    'contact_name' => 'Mr Ahmed',
                    'personal_note' => "Please review this request.\nThe file is attached.",
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('email.subject', 'Welcome Mr Ahmed')
            ->assertJsonPath('email.delivery_status', RequestEmailDeliveryStatus::QUEUED->value)
            ->assertJsonPath('email.email_template_id', $template->id)
            ->assertJsonPath('email.attachments.0.file_name', 'template-pack.pdf');

        $requestEmail = RequestEmail::query()->firstOrFail();

        $this->assertSame('Welcome Mr Ahmed', $requestEmail->subject);
        $this->assertStringContainsString('Dear Mr Ahmed', $requestEmail->body);
        $this->assertStringContainsString('Please review this request.', $requestEmail->body);
        $this->assertSame([
            'contact_name' => 'Mr Ahmed',
            'personal_note' => "Please review this request.\nThe file is attached.",
        ], $requestEmail->email_template_values_json);

        Bus::assertDispatchedAfterResponse(SendFinanceRequestEmailJob::class, function (SendFinanceRequestEmailJob $job) use ($requestEmail) {
            return $job->requestEmailId === $requestEmail->id;
        });
    }

    public function test_staff_email_options_include_active_request_email_templates_only(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $financeRequest = $this->createFinanceRequest($client, [
            'primary_staff_id' => $staff->id,
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
        ]);

        RequestEmailTemplate::create([
            'name' => 'Active welcome',
            'code' => 'active-welcome',
            'subject' => 'Welcome {{name}}',
            'body' => '<p>Welcome {{name}}</p>',
            'fields_json' => [
                [
                    'key' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'required' => true,
                ],
            ],
            'created_by' => $admin->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        RequestEmailTemplate::create([
            'name' => 'Inactive welcome',
            'code' => 'inactive-welcome',
            'subject' => 'Inactive',
            'body' => '<p>Inactive</p>',
            'fields_json' => [],
            'created_by' => $admin->id,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($staff)
            ->getJson("/api/staff/requests/{$financeRequest->id}/email-options");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'email_templates')
            ->assertJsonPath('email_templates.0.name', 'Active welcome')
            ->assertJsonPath('email_templates.0.fields_json.0.key', 'name');
    }

    private function createUser(string $role): User
    {
        $user = User::factory()->create([
            'account_type' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    private function createFinanceRequest(User $client, array $overrides = []): FinanceRequest
    {
        return FinanceRequest::create(array_merge([
            'reference_number' => 'REQ-TEST-'.Str::upper(Str::random(10)),
            'user_id' => $client->id,
            'applicant_type' => 'individual',
            'country_code' => 'OM',
            'status' => FinanceRequestStatus::SUBMITTED,
            'workflow_stage' => FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW,
            'understudy_status' => FinanceRequestUnderstudyStatus::DRAFT,
            'submitted_at' => now(),
            'latest_activity_at' => now(),
            'intake_details_json' => [
                'finance_type' => 'individual',
                'email' => $client->email,
            ],
        ], $overrides));
    }

    private function createContract(FinanceRequest $financeRequest, User $admin, array $overrides = []): Contract
    {
        $contract = Contract::create(array_merge([
            'finance_request_id' => $financeRequest->id,
            'contract_template_id' => null,
            'version_no' => 1,
            'contract_content' => '<p>Contract</p>',
            'contract_pdf_path' => "contracts/pdfs/request-{$financeRequest->id}.pdf",
            'generated_by' => $admin->id,
            'generated_at' => now(),
            'admin_signed_at' => now(),
            'admin_signed_by' => $admin->id,
            'admin_signature_path' => "contracts/signatures/admin-{$financeRequest->id}.png",
            'status' => ContractStatus::ADMIN_SIGNED,
            'contract_source' => 'generated',
            'client_signature_skipped' => false,
            'requires_commercial_registration' => false,
            'is_current' => true,
        ], $overrides));

        $this->storeIfPresent($contract->contract_pdf_path);
        $this->storeIfPresent($contract->admin_signature_path);
        $this->storeIfPresent($contract->client_signature_path);
        $this->storeIfPresent($contract->admin_uploaded_contract_path);
        $this->storeIfPresent($contract->client_commercial_contract_path);
        $this->storeIfPresent($contract->admin_commercial_contract_path);

        $financeRequest->forceFill([
            'current_contract_id' => $contract->id,
        ])->save();

        return $contract->fresh();
    }

    private function createRequiredUpload(
        FinanceRequest $financeRequest,
        DocumentUploadStep $step,
        User $uploader,
        string $fileName,
        string $filePath,
    ): RequestDocumentUpload {
        Storage::disk('public')->put($filePath, 'document');

        return RequestDocumentUpload::create([
            'finance_request_id' => $financeRequest->id,
            'document_upload_step_id' => $step->id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 1024,
            'status' => RequestDocumentUploadStatus::UPLOADED,
            'uploaded_by' => $uploader->id,
            'uploaded_at' => now(),
        ]);
    }

    private function assignStaffToRequest(
        FinanceRequest $financeRequest,
        User $staff,
        User $admin,
        bool $canRequestClientUpdates = false,
    ): FinanceRequestStaffAssignment {
        return FinanceRequestStaffAssignment::create([
            'finance_request_id' => $financeRequest->id,
            'staff_id' => $staff->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'lead',
            'is_primary' => true,
            'is_active' => true,
            'can_request_client_updates' => $canRequestClientUpdates,
            'assigned_at' => now(),
        ]);
    }

    private function storeIfPresent(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->put($path, 'file');
    }

    private function makeMailboxReady(User $user): void
    {
        $user->forceFill([
            'smtp_username' => $user->email,
            'smtp_sender_name' => $user->name,
            'smtp_password' => 'smtp-secret',
            'smtp_enabled' => true,
            'smtp_verified_at' => now(),
        ])->save();
    }

    private function signatureDataUrl(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
    }
}
