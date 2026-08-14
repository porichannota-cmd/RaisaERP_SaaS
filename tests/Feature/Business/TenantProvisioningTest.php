<?php

declare(strict_types=1);

namespace Tests\Feature\Business;

use App\Domain\Business\Enums\ProvisioningStatus;
use App\Domain\Business\Models\BusinessAddress;
use App\Domain\Business\Models\BusinessProfile;
use App\Domain\IAM\Models\MembershipRole;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\IAM\Services\TenantIamBootstrapper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_active_user_provisioning_denied(): void
    {
        $user = User::factory()->create(['account_status' => 'suspended']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Suspended Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        $response = $this->actingAs($user)->post(route('business.provision'));

        $response->assertServerError();
    }

    public function test_cross_user_provision_denied(): void
    {
        $owner = User::factory()->create(['account_status' => 'active']);
        $attacker = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $owner->id;
        $profile->legal_name = 'Owner Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        $response = $this->actingAs($attacker)->post(route('business.provision'));

        $response->assertNotFound(); // Handled by firstOrFail in controller
    }

    public function test_tenant_created_once_and_canonical_role_assigned(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'First Time Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        $response = $this->actingAs($user)->post(route('business.provision'));

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('tenants', 1);
        $tenant = Tenant::first();
        $this->assertEquals('First Time Corp', $tenant->name);

        $this->assertDatabaseCount('tenant_memberships', 1);
        $membership = TenantMembership::first();
        $this->assertEquals($user->id, $membership->user_id);
        $this->assertEquals($tenant->id, $membership->tenant_id);

        $role = Role::where('tenant_id', $tenant->id)
            ->where('code', TenantIamBootstrapper::CANONICAL_TENANT_ADMIN_CODE)
            ->first();
        $this->assertNotNull($role);

        $this->assertDatabaseHas('membership_roles', [
            'membership_id' => $membership->id,
            'role_id' => $role->id,
        ]);

        $profile->refresh();
        $this->assertEquals($tenant->id, $profile->tenant_id);
        $this->assertEquals(ProvisioningStatus::PROVISIONED, $profile->provisioning_status);
    }

    public function test_duplicate_provision_idempotent(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Idempotent Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        // First provision
        $this->actingAs($user)->post(route('business.provision'));
        $this->assertDatabaseCount('tenants', 1);

        // Second provision
        $this->actingAs($user)->post(route('business.provision'));
        
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('tenant_memberships', 1);
    }

    public function test_failure_during_iam_bootstrap_rolls_back_tenant(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Rollback Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        // Mock Bootstrapper to throw exception
        $this->mock(TenantIamBootstrapper::class, function ($mock) {
            $mock->shouldReceive('bootstrapForTenant')->andThrow(new \RuntimeException('Mocked IAM failure'));
        });

        $response = $this->actingAs($user)->post(route('business.provision'));
        
        $response->assertServerError();

        $this->assertDatabaseCount('tenants', 0); // Rolled back
        $this->assertDatabaseCount('tenant_memberships', 0);
        $this->assertDatabaseCount('membership_roles', 0);

        $profile->refresh();
        $this->assertEquals(ProvisioningStatus::READY_FOR_PROVISIONING, $profile->provisioning_status);
        $this->assertNull($profile->tenant_id);
    }
    public function test_effective_authorization_after_provisioning(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Effective Auth Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        $this->actingAs($user)->post(route('business.provision'));

        $tenant = Tenant::first();
        $this->assertNotNull($tenant);

        // Setup the tenant context
        \App\Domain\Tenant\ActiveTenantContext::set($tenant->id);

        /** @var \App\Domain\IAM\Services\AuthorizationResolver $resolver */
        $resolver = $this->app->make(\App\Domain\IAM\Services\AuthorizationResolver::class);

        $this->assertTrue($resolver->check($user, 'tenant.workspace.access'));

        // Switch to a different tenant where user has no membership
        $otherTenant = Tenant::create(['name' => 'Other Tenant']);
        \App\Domain\Tenant\ActiveTenantContext::set($otherTenant->id);

        $this->assertFalse($resolver->check($user, 'tenant.workspace.access'));
        \App\Domain\Tenant\ActiveTenantContext::clear();
    }

    public function test_cross_tenant_owner_isolation(): void
    {
        $userA = User::factory()->create(['account_status' => 'active']);
        $profileA = new BusinessProfile();
        $profileA->owner_user_id = $userA->id;
        $profileA->legal_name = 'Corp A';
        $profileA->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profileA->save();
        $this->actingAs($userA)->post(route('business.provision'));
        $tenantA = Tenant::where('name', 'Corp A')->first();

        $userB = User::factory()->create(['account_status' => 'active']);
        $profileB = new BusinessProfile();
        $profileB->owner_user_id = $userB->id;
        $profileB->legal_name = 'Corp B';
        $profileB->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profileB->save();
        $this->actingAs($userB)->post(route('business.provision'));
        $tenantB = Tenant::where('name', 'Corp B')->first();

        /** @var \App\Domain\IAM\Services\AuthorizationResolver $resolver */
        $resolver = $this->app->make(\App\Domain\IAM\Services\AuthorizationResolver::class);

        // User A context
        \App\Domain\Tenant\ActiveTenantContext::set($tenantA->id);
        $this->assertTrue($resolver->check($userA, 'tenant.workspace.access'));
        $this->assertFalse($resolver->check($userB, 'tenant.workspace.access'));

        // User B context
        \App\Domain\Tenant\ActiveTenantContext::set($tenantB->id);
        $this->assertTrue($resolver->check($userB, 'tenant.workspace.access'));
        $this->assertFalse($resolver->check($userA, 'tenant.workspace.access'));

        \App\Domain\Tenant\ActiveTenantContext::clear();
    }

    public function test_account_status_preservation(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Status Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        $this->actingAs($user)->post(route('business.provision'));

        $user->refresh();
        $this->assertEquals('active', $user->account_status->value);

        // Failed provisioning shouldn't alter it either
        $user2 = User::factory()->create(['account_status' => 'active']);
        $profile2 = new BusinessProfile();
        $profile2->owner_user_id = $user2->id;
        $profile2->legal_name = 'Fail Corp';
        $profile2->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile2->save();

        $this->mock(TenantIamBootstrapper::class, function ($mock) {
            $mock->shouldReceive('bootstrapForTenant')->andThrow(new \RuntimeException('Mocked IAM failure'));
        });

        $this->actingAs($user2)->post(route('business.provision'));

        $user2->refresh();
        $this->assertEquals('active', $user2->account_status->value);
    }

    public function test_membership_failure_rollback(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Membership Fail Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        \Illuminate\Support\Facades\Event::listen('eloquent.creating: ' . TenantMembership::class, function () {
            throw new \RuntimeException('Mocked membership failure');
        });

        $response = $this->actingAs($user)->post(route('business.provision'));
        $response->assertServerError();

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('tenant_memberships', 0);
        $this->assertDatabaseCount('membership_roles', 0);

        $profile->refresh();
        $this->assertNull($profile->tenant_id);
        $this->assertEquals(ProvisioningStatus::READY_FOR_PROVISIONING, $profile->provisioning_status);
    }

    public function test_role_assignment_failure_rollback(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Role Assign Fail Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        \Illuminate\Support\Facades\Event::listen('eloquent.creating: ' . MembershipRole::class, function () {
            throw new \RuntimeException('Mocked role failure');
        });

        $response = $this->actingAs($user)->post(route('business.provision'));
        $response->assertServerError();

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('tenant_memberships', 0);
        $this->assertDatabaseCount('membership_roles', 0);

        $profile->refresh();
        $this->assertNull($profile->tenant_id);
        $this->assertEquals(ProvisioningStatus::READY_FOR_PROVISIONING, $profile->provisioning_status);
    }

    public function test_business_link_failure_rollback(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Link Fail Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        \Illuminate\Support\Facades\Event::listen('eloquent.updating: ' . BusinessProfile::class, function () {
            throw new \RuntimeException('Mocked business link failure');
        });

        $response = $this->actingAs($user)->post(route('business.provision'));
        $response->assertServerError();

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('tenant_memberships', 0);
        $this->assertDatabaseCount('membership_roles', 0);

        $profile->refresh();
        $this->assertNull($profile->tenant_id);
        $this->assertEquals(ProvisioningStatus::READY_FOR_PROVISIONING, $profile->provisioning_status);
    }

    public function test_platform_iam_preservation(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Platform IAM Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        $beforeCount = DB::table('platform_reviewer_assignments')->count();

        $this->actingAs($user)->post(route('business.provision'));

        $afterCount = DB::table('platform_reviewer_assignments')->count();
        $this->assertEquals($beforeCount, $afterCount);
    }

    public function test_subscription_business_review_media_non_side_effect(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $profile = new BusinessProfile();
        $profile->owner_user_id = $user->id;
        $profile->legal_name = 'Side Effect Corp';
        $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
        $profile->save();

        $this->actingAs($user)->post(route('business.provision'));

        // Assert no media assets created
        $this->assertDatabaseCount('media_assets', 0);
        
        // Assert no review requests created
        $this->assertDatabaseCount('account_review_requests', 0);
    }
}
