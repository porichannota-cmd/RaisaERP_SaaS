<?php

namespace Tests\Feature\Tenant;

use App\Domain\IAM\Enums\MembershipStatus;
use App\Domain\IAM\Models\MembershipRole;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\Tenant\ActiveTenantContext;
use App\Models\User;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class TenantWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createTenantWithAdmin(User $user): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Test Business',
            'status' => 'active',
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $adminRole = Role::firstOrCreate(
            ['type' => RoleType::TENANT_SYSTEM, 'code' => 'TENANT_ADMIN', 'tenant_id' => $tenant->id],
            ['name' => 'Tenant Admin', 'description' => 'System tenant admin']
        );

        $permission = \App\Domain\IAM\Models\Permission::firstOrCreate(
            ['key' => 'tenant.workspace.access'],
            ['name' => 'Workspace Access', 'description' => 'Access tenant workspace']
        );

        \App\Domain\IAM\Models\AuthorizationGrant::firstOrCreate([
            'role_id' => $adminRole->id,
            'permission_key' => $permission->key,
            'scope_type' => \App\Domain\IAM\Enums\AuthScope::TENANT,
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id' => $adminRole->id,
            'effective_from' => now(),
        ]);

        return $tenant;
    }

    public function test_unauthenticated_workspace_list_denied()
    {
        $this->get(route('workspaces.index'))->assertRedirect(route('login'));
    }

    public function test_active_user_with_membership_sees_own_tenant()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->get(route('workspaces.index'))
             ->assertOk()
             ->assertInertia(fn ($page) => $page->component('Business/Workspaces/Index')
                 ->has('workspaces', 1)
                 ->where('workspaces.0.id', $tenant->id));
    }

    public function test_non_member_tenant_excluded()
    {
        $user1 = User::factory()->create();
        $tenant1 = $this->createTenantWithAdmin($user1);

        $user2 = User::factory()->create();
        $tenant2 = $this->createTenantWithAdmin($user2);

        $this->actingAs($user1)
             ->get(route('workspaces.index'))
             ->assertInertia(fn ($page) => $page->has('workspaces', 1)->where('workspaces.0.id', $tenant1->id));
    }

    public function test_valid_workspace_switch_succeeds()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id])
             ->assertRedirect(route('dashboard'))
             ->assertSessionHas('active_tenant_id', $tenant->id);
    }

    public function test_forged_tenant_switch_denied()
    {
        $user1 = User::factory()->create();
        $tenant1 = $this->createTenantWithAdmin($user1);

        $user2 = User::factory()->create();

        $this->actingAs($user2)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant1->id])
             ->assertForbidden()
             ->assertSessionMissing('active_tenant_id');
    }

    public function test_missing_active_tenant_redirects_to_workspaces()
    {
        $user = User::factory()->create();
        $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertRedirect(route('workspaces.index'));
    }

    public function test_active_context_resolves_on_protected_route()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        // Pre-set session
        Session::put('active_tenant_id', $tenant->id);

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertOk()
             ->assertInertia(fn ($page) => $page->component('dashboard')
                 ->where('activeWorkspace.id', $tenant->id));
    }

    public function test_revoked_membership_clears_stale_context()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        Session::put('active_tenant_id', $tenant->id);

        // Revoke membership
        TenantMembership::where('tenant_id', $tenant->id)->update(['status' => MembershipStatus::SUSPENDED]);

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertRedirect(route('workspaces.index'))
             ->assertSessionMissing('active_tenant_id');

        $this->assertFalse(ActiveTenantContext::isSet());
    }

    public function test_logout_clears_context()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        Session::put('active_tenant_id', $tenant->id);

        $this->actingAs($user)
             ->post(route('logout'))
             ->assertRedirect('/');

        $this->assertNull(Session::get('active_tenant_id'));
    }

    public function test_client_injected_parameters_ignored()
    {
        $user1 = User::factory()->create();
        $tenant1 = $this->createTenantWithAdmin($user1);

        $user2 = User::factory()->create();
        $tenant2 = $this->createTenantWithAdmin($user2);

        $this->actingAs($user2)
             ->post(route('workspaces.switch'), [
                 'tenant_id' => $tenant1->id,
                 'user_id' => $user1->id, // Try to forge user
                 'role_id' => 'ADMIN',
             ])
             ->assertForbidden()
             ->assertSessionMissing('active_tenant_id');
    }
    public function test_role_without_grant_denied()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        
        \App\Domain\IAM\Models\AuthorizationGrant::where('role_id', \App\Domain\IAM\Models\Role::first()->id)->delete();

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id])
             ->assertForbidden();
    }

    public function test_platform_reviewer_cannot_substitute()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        
        // Remove tenant membership to simulate an external platform reviewer
        TenantMembership::where('user_id', $user->id)->delete();

        // Assign real platform reviewer capability
        \App\Models\PlatformReviewerAssignment::factory()->create([
            'user_id' => $user->id,
            'capability' => 'ACCOUNT_REVIEW',
            'status' => 'active',
        ]);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id])
             ->assertForbidden();
    }

    public function test_switching_a_to_b_replaces_pointer()
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantWithAdmin($user);
        $tenantB = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenantA->id])
             ->assertSessionHas('active_tenant_id', $tenantA->id);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenantB->id])
             ->assertSessionHas('active_tenant_id', $tenantB->id);
    }

    public function test_invalid_switch_does_not_create_authority()
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantWithAdmin($user);
        
        $user2 = User::factory()->create();
        $tenantB = $this->createTenantWithAdmin($user2);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenantB->id])
             ->assertForbidden()
             ->assertSessionMissing('active_tenant_id');
    }

    public function test_permission_removed_after_switch_rejected_next_request()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        Session::put('active_tenant_id', $tenant->id);

        \App\Domain\IAM\Models\AuthorizationGrant::where('role_id', \App\Domain\IAM\Models\Role::first()->id)->delete();

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertRedirect(route('workspaces.index'));
    }

    public function test_leave_clears_context()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        Session::put('active_tenant_id', $tenant->id);

        $this->actingAs($user)
             ->post(route('workspaces.leave'))
             ->assertRedirect(route('workspaces.index'))
             ->assertSessionMissing('active_tenant_id');
    }

    public function test_account_lifecycle_denial_preserved_blocked()
    {
        $user = User::factory()->create(['account_status' => \App\Domain\Registration\Enums\AccountStatus::BLOCKED]);
        $tenant = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->get(route('workspaces.index'))
             ->assertForbidden();
             
        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id])
             ->assertForbidden();
    }
    
    public function test_account_lifecycle_denial_preserved_suspended()
    {
        $user = User::factory()->create(['account_status' => \App\Domain\Registration\Enums\AccountStatus::SUSPENDED]);
        $tenant = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->get(route('workspaces.index'))
             ->assertForbidden();
    }
    
    public function test_account_lifecycle_denial_preserved_rejected()
    {
        $user = User::factory()->create(['account_status' => \App\Domain\Registration\Enums\AccountStatus::REJECTED]);
        $tenant = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->get(route('workspaces.index'))
             ->assertForbidden();
    }
    
    public function test_account_lifecycle_denial_preserved_pending_approval()
    {
        $user = User::factory()->create(['account_status' => \App\Domain\Registration\Enums\AccountStatus::PENDING_APPROVAL]);
        $tenant = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->get(route('workspaces.index'))
             ->assertForbidden();
    }

    public function test_account_lifecycle_denial_preserved_profile_incomplete()
    {
        $user = User::factory()->create(['account_status' => \App\Domain\Registration\Enums\AccountStatus::PROFILE_INCOMPLETE]);
        $tenant = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->get(route('workspaces.index'))
             ->assertForbidden();
    }

    public function test_authentication_vs_workspace_separation()
    {
        $policy = new \App\Domain\Registration\Policies\AccountAccessPolicy();
        
        $profileIncomplete = User::factory()->make(['account_status' => \App\Domain\Registration\Enums\AccountStatus::PROFILE_INCOMPLETE]);
        $this->assertTrue($profileIncomplete->account_status->mayAuthenticate());
        $this->assertFalse($policy->mayAccessWorkspace($profileIncomplete));
        
        $pendingApproval = User::factory()->make(['account_status' => \App\Domain\Registration\Enums\AccountStatus::PENDING_APPROVAL]);
        $this->assertTrue($pendingApproval->account_status->mayAuthenticate());
        $this->assertFalse($policy->mayAccessWorkspace($pendingApproval));

        $rejected = User::factory()->make(['account_status' => \App\Domain\Registration\Enums\AccountStatus::REJECTED]);
        $this->assertTrue($rejected->account_status->mayAuthenticate());
        $this->assertFalse($policy->mayAccessWorkspace($rejected));

        $active = User::factory()->make(['account_status' => \App\Domain\Registration\Enums\AccountStatus::ACTIVE]);
        $this->assertTrue($active->account_status->mayAuthenticate());
        $this->assertTrue($policy->mayAccessWorkspace($active));

        $suspended = User::factory()->make(['account_status' => \App\Domain\Registration\Enums\AccountStatus::SUSPENDED]);
        $this->assertFalse($suspended->account_status->mayAuthenticate());
        $this->assertFalse($policy->mayAccessWorkspace($suspended));

        $blocked = User::factory()->make(['account_status' => \App\Domain\Registration\Enums\AccountStatus::BLOCKED]);
        $this->assertFalse($blocked->account_status->mayAuthenticate());
        $this->assertFalse($policy->mayAccessWorkspace($blocked));
    }

    public function test_long_lived_worker_safety_no_leakage()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        
        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id]);
             
        // After request, it must be cleared
        $this->assertFalse(ActiveTenantContext::isSet());
    }

    public function test_exception_cleanup_try_finally()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        Session::put('active_tenant_id', $tenant->id);
        
        // Mock route that throws
        \Illuminate\Support\Facades\Route::get('/throw', function () {
            throw new \Exception('Simulated controller crash');
        })->middleware(['web', 'auth', 'tenant.active']);

        try {
            $this->actingAs($user)->get('/throw');
        } catch (\Exception $e) {
            $this->assertEquals('Simulated controller crash', $e->getMessage());
        }

        $this->assertFalse(ActiveTenantContext::isSet());
    }

    public function test_inactive_membership_switch_denied()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        TenantMembership::where('tenant_id', $tenant->id)->update(['status' => MembershipStatus::PENDING]);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id])
             ->assertForbidden();
    }

    public function test_revoked_membership_switch_denied()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        TenantMembership::where('tenant_id', $tenant->id)->update(['status' => MembershipStatus::SUSPENDED]);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id])
             ->assertForbidden();
    }

    public function test_nonexistent_tenant_denied()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => 'INVALID123'])
             ->assertInvalid(['tenant_id']);
    }

    public function test_cross_user_tenant_denied()
    {
        $user1 = User::factory()->create();
        $tenant1 = $this->createTenantWithAdmin($user1);
        $user2 = User::factory()->create();

        $this->actingAs($user2)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant1->id])
             ->assertForbidden();
    }

    public function test_missing_workspace_permission_denied()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        $otherPermission = \App\Domain\IAM\Models\Permission::create(['key' => 'other.permission', 'name' => 'Other', 'description' => 'Other']);
        \App\Domain\IAM\Models\AuthorizationGrant::where('role_id', \App\Domain\IAM\Models\Role::first()->id)->update(['permission_key' => $otherPermission->key]);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id])
             ->assertForbidden();
    }

    public function test_canonical_tenant_admin_permission_succeeds()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);

        $this->actingAs($user)
             ->post(route('workspaces.switch'), ['tenant_id' => $tenant->id])
             ->assertRedirect(route('dashboard'));
    }

    public function test_stale_session_rejected_by_middleware()
    {
        $user = User::factory()->create();
        Session::put('active_tenant_id', 'NON_EXISTENT');

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertRedirect(route('workspaces.index'))
             ->assertSessionMissing('active_tenant_id');
    }

    public function test_deleted_missing_tenant_rejected()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        Session::put('active_tenant_id', $tenant->id);
        $tenant->delete();

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertRedirect(route('workspaces.index'));
    }

    public function test_active_tenant_context_matches_selected_tenant_during_request()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        Session::put('active_tenant_id', $tenant->id);

        \Illuminate\Support\Facades\Route::get('/test-context', function () {
            return ActiveTenantContext::get();
        })->middleware(['web', 'auth', 'tenant.active']);

        $this->actingAs($user)
             ->get('/test-context')
             ->assertSee($tenant->id);
    }

    public function test_dashboard_requires_tenant_context()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertRedirect(route('workspaces.index'));
    }

    public function test_dashboard_accepts_valid_context()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        Session::put('active_tenant_id', $tenant->id);

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertOk();
    }

    public function test_dashboard_rejects_unauthorized_context()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user);
        Session::put('active_tenant_id', $tenant->id);
        TenantMembership::where('tenant_id', $tenant->id)->update(['status' => MembershipStatus::SUSPENDED]);

        $this->actingAs($user)
             ->get(route('dashboard'))
             ->assertRedirect(route('workspaces.index'));
    }

    public function test_workspace_list_privacy()
    {
        $user1 = User::factory()->create();
        $tenant1 = $this->createTenantWithAdmin($user1);
        $user2 = User::factory()->create();
        $tenant2 = $this->createTenantWithAdmin($user2);

        $this->actingAs($user1)
             ->get(route('workspaces.index'))
             ->assertInertia(fn ($page) => $page->where('workspaces.0.id', $tenant1->id)->missing('workspaces.1'));
    }
}
