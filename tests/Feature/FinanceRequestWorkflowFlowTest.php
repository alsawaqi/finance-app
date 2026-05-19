<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestStaffQuestionStatus;
use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestUnderstudyStatus;
use App\Enums\FinanceRequestWorkflowStage;
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
use App\Models\RequestDocumentUpload;
use App\Models\RequestEmail;
use App\Models\RequestTimeline;
use App\Models\User;
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

    public function test_admin_cannot_manually_skip_workflow_steps(): void
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
            ->assertStatus(422)
            ->assertJsonValidationErrors('workflow_stage');

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'workflow_stage' => FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
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

    public function test_manual_workflow_stage_change_cannot_bypass_approval_side_effects(): void
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
            ->assertStatus(422)
            ->assertJsonValidationErrors('workflow_stage');

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'status' => FinanceRequestStatus::SUBMITTED->value,
            'workflow_stage' => FinanceRequestWorkflowStage::SUBMITTED_FOR_REVIEW->value,
            'approval_reference_number' => null,
        ]);
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
        ]], $timeline->metadata_json['active_staff']);
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

    public function test_understudy_approval_requires_required_questions_to_be_reviewed_closed(): void
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
            ->assertJsonPath('staff_question_summary.can_advance_from_understudy', false);

        $approveResponse = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/understudy-review", [
                'action' => 'approve',
            ]);

        $approveResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('staff_questions');

        $advanceResponse = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/advance-understudy", [
                'review_note' => 'Trying to bypass review.',
            ]);

        $advanceResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('staff_questions');

        $rejectResponse = $this
            ->actingAs($admin)
            ->postJson("/api/admin/requests/{$financeRequest->id}/understudy-review", [
                'action' => 'reject',
                'review_note' => 'Please refine the answer.',
            ]);

        $rejectResponse->assertOk();

        $this->assertDatabaseHas('finance_requests', [
            'id' => $financeRequest->id,
            'understudy_status' => FinanceRequestUnderstudyStatus::REJECTED->value,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_STAFF_ANSWERS->value,
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
