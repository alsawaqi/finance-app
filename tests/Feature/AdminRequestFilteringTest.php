<?php

namespace Tests\Feature;

use App\Enums\FinanceRequestStatus;
use App\Enums\FinanceRequestUnderstudyStatus;
use App\Enums\FinanceRequestWorkflowStage;
use App\Models\FinanceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRequestFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_filter_requests_by_request_number_and_client(): void
    {
        $admin = $this->createUser('admin');
        $firstClient = $this->createUser('client', ['name' => 'Amina Client']);
        $secondClient = $this->createUser('client', ['name' => 'Nasser Client']);

        $targetRequest = $this->createFinanceRequest($firstClient, [
            'reference_number' => 'REQ-FILTER-0001',
        ]);

        $otherRequest = $this->createFinanceRequest($secondClient, [
            'reference_number' => 'REQ-FILTER-9999',
        ]);

        $numberResponse = $this
            ->actingAs($admin)
            ->getJson('/api/admin/request-filters?request_number=0001&per_page=10');

        $numberResponse
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('summary.total_requests', 1)
            ->assertJsonPath('summary.unique_clients', 1)
            ->assertJsonPath('requests.0.id', $targetRequest->id)
            ->assertJsonPath('requests.0.reference_number', 'REQ-FILTER-0001');

        $clientResponse = $this
            ->actingAs($admin)
            ->getJson("/api/admin/request-filters?client_id={$secondClient->id}&per_page=10");

        $clientResponse
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('requests.0.id', $otherRequest->id)
            ->assertJsonPath('requests.0.client.id', $secondClient->id)
            ->assertJsonFragment([
                'id' => $firstClient->id,
                'name' => 'Amina Client',
                'email' => $firstClient->email,
            ]);
    }

    public function test_admin_can_delete_a_request_from_filtering(): void
    {
        $admin = $this->createUser('admin');
        $client = $this->createUser('client');
        $financeRequest = $this->createFinanceRequest($client, [
            'reference_number' => 'REQ-DELETE-0001',
            'status' => FinanceRequestStatus::COMPLETED,
            'workflow_stage' => FinanceRequestWorkflowStage::COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson("/api/admin/request-filters/{$financeRequest->id}");

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Request deleted successfully.');

        $this->assertDatabaseMissing('finance_requests', [
            'id' => $financeRequest->id,
        ]);
    }

    public function test_admin_can_group_requests_by_client_and_open_client_requests(): void
    {
        $admin = $this->createUser('admin');
        $firstClient = $this->createUser('client', ['name' => 'Abdulaziz Client']);
        $secondClient = $this->createUser('client', ['name' => 'Abdullah Client']);
        $clientWithoutRequests = $this->createUser('client', ['name' => 'No Requests Client']);

        $firstRequest = $this->createFinanceRequest($firstClient, [
            'reference_number' => 'REQ-CLIENT-0001',
        ]);
        $secondRequest = $this->createFinanceRequest($firstClient, [
            'reference_number' => 'REQ-CLIENT-0002',
        ]);
        $this->createFinanceRequest($secondClient, [
            'reference_number' => 'REQ-CLIENT-0003',
        ]);

        $overviewResponse = $this
            ->actingAs($admin)
            ->getJson('/api/admin/clients-overview?state=all&with_requests=true&per_page=10');

        $overviewResponse
            ->assertOk()
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('clients.0.id', $firstClient->id)
            ->assertJsonPath('clients.0.requests_count', 2)
            ->assertJsonPath('clients.1.id', $secondClient->id)
            ->assertJsonMissing([
                'id' => $clientWithoutRequests->id,
                'name' => 'No Requests Client',
            ]);

        $requestsResponse = $this
            ->actingAs($admin)
            ->getJson("/api/admin/clients-overview/{$firstClient->id}/requests?per_page=10");

        $requestsResponse
            ->assertOk()
            ->assertJsonPath('client.id', $firstClient->id)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonFragment(['reference_number' => $firstRequest->reference_number])
            ->assertJsonFragment(['reference_number' => $secondRequest->reference_number]);
    }

    public function test_admin_can_delete_a_client_and_their_requests(): void
    {
        $admin = $this->createUser('admin');
        $client = $this->createUser('client', ['name' => 'Client To Delete']);
        $financeRequest = $this->createFinanceRequest($client, [
            'reference_number' => 'REQ-CLIENT-DELETE-0001',
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson("/api/admin/clients-overview/{$client->id}");

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Client deleted successfully.');

        $this->assertDatabaseMissing('users', [
            'id' => $client->id,
        ]);
        $this->assertDatabaseMissing('finance_requests', [
            'id' => $financeRequest->id,
        ]);
    }

    private function createUser(string $role, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'account_type' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));

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
            'status' => FinanceRequestStatus::ACTIVE,
            'workflow_stage' => FinanceRequestWorkflowStage::PROCESSING,
            'understudy_status' => FinanceRequestUnderstudyStatus::DRAFT,
            'submitted_at' => now(),
            'latest_activity_at' => now(),
            'intake_details_json' => [
                'finance_type' => 'individual',
                'email' => $client->email,
            ],
        ], $overrides));
    }
}
