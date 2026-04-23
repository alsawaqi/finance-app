<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\FinanceRequestStaffQuestionStatus;
use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestUnderstudyStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Enums\RequestEmailDeliveryStatus;
use App\Enums\RequestDocumentUploadStatus;
use App\Jobs\SendFinanceRequestEmailJob;
use App\Models\Agent;
use App\Models\Bank;
use App\Models\Contract;
use App\Models\DocumentUploadStep;
use App\Models\FinanceRequest;
use App\Models\FinanceRequestAgentAssignment;
use App\Models\FinanceRequestAgentAssignmentDocument;
use App\Models\FinanceRequestStaffQuestion;
use App\Models\RequestEmail;
use App\Models\RequestDocumentUpload;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_commercial_registration_branch_moves_from_client_upload_to_admin_upload_then_staff_assignment(): void
    {
        $client = $this->createUser('client');
        $admin = $this->createUser('admin');
        $financeRequest = $this->createFinanceRequest($client, [
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::AWAITING_CLIENT_COMMERCIAL_REGISTRATION_UPLOAD,
            'approved_at' => now(),
            'approval_reference_number' => 'APR-2026-000003',
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
            'approval_reference_number' => 'APR-2026-000004',
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
            'approval_reference_number' => 'APR-2026-000005',
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
            'reference_number' => 'REQ-TEST-' . Str::upper(Str::random(10)),
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
}
