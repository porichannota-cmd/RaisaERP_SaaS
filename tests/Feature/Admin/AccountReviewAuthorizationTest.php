<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Registration\Enums\AccountStatus;
use App\Models\AccountReviewRequest;
use App\Models\PlatformReviewerAssignment;
use App\Domain\IAM\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountReviewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createReviewer(string $status = 'ACTIVE'): User
    {
        $user = User::factory()->create();
        PlatformReviewerAssignment::factory()->create([
            'user_id' => $user->id,
            'capability' => 'ACCOUNT_REVIEW',
            'status' => $status,
        ]);

        return $user;
    }

    public function test_unauthenticated_queue_access_is_denied(): void
    {
        $response = $this->get('/admin/approvals');
        $response->assertRedirect('/login'); // Standard auth middleware redirect
    }

    public function test_normal_authenticated_user_denied(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/approvals');
        $response->assertStatus(403);
    }

    public function test_active_reviewer_allowed(): void
    {
        $reviewer = $this->createReviewer();

        $response = $this->actingAs($reviewer)->get('/admin/approvals');
        $response->assertStatus(200);
    }

    public function test_revoked_reviewer_denied(): void
    {
        $reviewer = $this->createReviewer('REVOKED');

        $response = $this->actingAs($reviewer)->get('/admin/approvals');
        $response->assertStatus(403);
    }

    public function test_cross_user_reviewer_isolation(): void
    {
        $this->createReviewer(); // User A gets assignment
        $userB = User::factory()->create(); // User B gets nothing

        $response = $this->actingAs($userB)->get('/admin/approvals');
        $response->assertStatus(403);
    }

    public function test_client_capability_fabrication_is_ignored(): void
    {
        $user = User::factory()->create();

        // Attempt to fabricate query params
        $response = $this->actingAs($user)->get('/admin/approvals?capability=ACCOUNT_REVIEW&is_admin=true&role=admin');
        $response->assertStatus(403);
    }

    public function test_approve_endpoint_authorization(): void
    {
        $targetUser = User::factory()->create(['account_status' => AccountStatus::PENDING_APPROVAL]);
        $request = AccountReviewRequest::create(['user_id' => $targetUser->id, 'status' => 'PENDING']);

        // Normal user denied
        $normalUser = User::factory()->create();
        $this->actingAs($normalUser)->post("/admin/approvals/{$request->id}/approve")->assertStatus(403);

        // Revoked reviewer denied
        $revokedReviewer = $this->createReviewer('REVOKED');
        $this->actingAs($revokedReviewer)->post("/admin/approvals/{$request->id}/approve")->assertStatus(403);

        // Active reviewer allowed
        $activeReviewer = $this->createReviewer();
        $this->actingAs($activeReviewer)->post("/admin/approvals/{$request->id}/approve")->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals('APPROVED', $request->fresh()->status);
    }

    public function test_reject_endpoint_authorization(): void
    {
        $targetUser = User::factory()->create(['account_status' => AccountStatus::PENDING_APPROVAL]);
        $request = AccountReviewRequest::create(['user_id' => $targetUser->id, 'status' => 'PENDING']);
        $payload = ['reason' => 'Invalid document'];

        // Normal user denied
        $normalUser = User::factory()->create();
        $this->actingAs($normalUser)->post("/admin/approvals/{$request->id}/reject", $payload)->assertStatus(403);

        // Revoked reviewer denied
        $revokedReviewer = $this->createReviewer('REVOKED');
        $this->actingAs($revokedReviewer)->post("/admin/approvals/{$request->id}/reject", $payload)->assertStatus(403);

        // Active reviewer allowed
        $activeReviewer = $this->createReviewer();
        $this->actingAs($activeReviewer)->post("/admin/approvals/{$request->id}/reject", $payload)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals('REJECTED', $request->fresh()->status);
    }

    public function test_cross_request_idor_protection(): void
    {
        $targetUser1 = User::factory()->create(['account_status' => AccountStatus::PENDING_APPROVAL]);
        $request1 = AccountReviewRequest::create(['user_id' => $targetUser1->id, 'status' => 'PENDING']);

        $normalUser = User::factory()->create();

        // Normal user tries to approve someone else's request
        $this->actingAs($normalUser)->post("/admin/approvals/{$request1->id}/approve")->assertStatus(403);
    }

    public function test_self_elevation_protection(): void
    {
        // Assert no public route exists to assign reviewer capability.
        // We do this by ensuring we can't POST to /admin/approvals/assign or similar.
        // Since there is no such route defined, a POST would 404 or 403.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/platform-reviewers', [
            'capability' => 'ACCOUNT_REVIEW',
        ]);

        // Should be 404 since it's not a registered route
        $response->assertStatus(404);

        $this->assertEquals(0, PlatformReviewerAssignment::count());
    }

    public function test_tenant_non_side_effect(): void
    {
        $reviewer = $this->createReviewer();
        $targetUser = User::factory()->create(['account_status' => AccountStatus::PENDING_APPROVAL]);
        $request = AccountReviewRequest::create(['user_id' => $targetUser->id, 'status' => 'PENDING']);

        $initialTenantCount = Tenant::count();

        $this->actingAs($reviewer)->post("/admin/approvals/{$request->id}/approve");

        $this->assertEquals($initialTenantCount, Tenant::count(), 'No tenant should be created during approval.');
    }

    public function test_queue_pii_minimization(): void
    {
        $reviewer = $this->createReviewer();

        $targetUser = User::factory()->create([
            'account_status' => AccountStatus::PENDING_APPROVAL,
            'password' => bcrypt('secretpassword'),
        ]);
        AccountReviewRequest::create(['user_id' => $targetUser->id, 'status' => 'PENDING']);

        $response = $this->actingAs($reviewer)->get('/admin/approvals');
        $response->assertStatus(200);

        // Fetch Inertia props
        $page = $response->viewData('page');
        $requests = $page['props']['requests']['data'];

        $this->assertNotEmpty($requests);
        $userPayload = $requests[0]['user'];

        // Should have allowed fields
        $this->assertArrayHasKey('id', $userPayload);
        $this->assertArrayHasKey('name', $userPayload);
        $this->assertArrayHasKey('email', $userPayload);
        $this->assertArrayHasKey('account_status', $userPayload);

        // Should NOT have PII/Secrets
        $this->assertArrayNotHasKey('password', $userPayload);
        $this->assertArrayNotHasKey('nid_number', $userPayload); // assuming standard User model fields
    }
}
