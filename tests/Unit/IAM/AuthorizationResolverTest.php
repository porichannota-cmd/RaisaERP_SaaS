<?php

namespace Tests\Unit\IAM;

use App\Domain\IAM\Enums\AuthScope;
use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\AuthorizationGrant;
use App\Domain\IAM\Models\MembershipRole;
use App\Domain\IAM\Models\Permission;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\IAM\Services\AuthorizationResolver;
use App\Domain\IAM\Services\MembershipRoleService;
use App\Domain\Tenant\ActiveTenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_deny_when_no_tenant_context()
    {
        $user = User::factory()->create();
        $resolver = new AuthorizationResolver;

        ActiveTenantContext::clear();
        $this->assertFalse($resolver->check($user, 'users.view'));
    }

    public function test_default_deny_when_no_membership()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();

        ActiveTenantContext::set($tenant->id);

        $resolver = new AuthorizationResolver;
        $this->assertFalse($resolver->check($user, 'users.view'));
    }

    public function test_allows_action_with_valid_tenant_role_and_permission()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();

        ActiveTenantContext::set($tenant->id);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'type' => RoleType::TENANT_CUSTOM,
        ]);

        Permission::create(['key' => 'users.view']);

        AuthorizationGrant::create([
            'role_id' => $role->id,
            'permission_key' => 'users.view',
            'scope_type' => AuthScope::TENANT,
        ]);

        $service = new MembershipRoleService;
        $service->assignRole($membership, $role);

        $resolver = new AuthorizationResolver;
        $this->assertTrue($resolver->check($user, 'users.view'));
    }

    public function test_gate_integration_works()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();

        ActiveTenantContext::set($tenant->id);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'type' => RoleType::TENANT_CUSTOM,
        ]);

        Permission::create(['key' => 'something.do']);
        Permission::create(['key' => 'something.deny']);

        AuthorizationGrant::create([
            'role_id' => $role->id,
            'permission_key' => 'something.do',
            'scope_type' => AuthScope::TENANT,
        ]);

        $service = new MembershipRoleService;
        $service->assignRole($membership, $role);

        // A. Valid scoped grant -> ALLOW
        $this->assertTrue(Gate::forUser($user)->allows('something.do'));

        // B. No grant (but is authoritative) -> DENY
        $this->assertFalse(Gate::forUser($user)->allows('something.deny'));

        // C, D, E logic is already tested by resolver returning false.
        // Here we test F and G: Unrelated policy fallback vs definite deny.

        // F: Unrelated later gate cannot accidentally turn an IAM denial into ALLOW
        Gate::define('something.deny', function () {
            return true; // Malicious or accidental override
        });

        // Should STILL be false because IAM explicitly returned false in Gate::before
        $this->assertFalse(Gate::forUser($user)->allows('something.deny'));

        // G: Legitimate non-IAM policies are not unintentionally disabled
        Gate::define('external.policy', function () {
            return true;
        });

        // 'external.policy' is NOT in the permissions table, so Gate::before returns null
        // Then the external.policy definition runs and returns true
        $this->assertTrue(Gate::forUser($user)->allows('external.policy'));
    }

    public function test_future_effective_role_is_denied()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();
        ActiveTenantContext::set($tenant->id);

        $membership = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'type' => RoleType::TENANT_CUSTOM]);
        Permission::create(['key' => 'users.view']);
        AuthorizationGrant::create(['role_id' => $role->id, 'permission_key' => 'users.view', 'scope_type' => AuthScope::TENANT]);

        $assignment = MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'effective_from' => now()->addDays(5)->toDateString(),
        ]);

        $resolver = new AuthorizationResolver;
        $this->assertFalse($resolver->check($user, 'users.view'));
    }

    public function test_expired_role_is_denied()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();
        ActiveTenantContext::set($tenant->id);

        $membership = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'type' => RoleType::TENANT_CUSTOM]);
        Permission::create(['key' => 'users.view']);
        AuthorizationGrant::create(['role_id' => $role->id, 'permission_key' => 'users.view', 'scope_type' => AuthScope::TENANT]);

        $assignment = MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'effective_from' => now()->subDays(10)->toDateString(),
            'effective_until' => now()->subDays(2)->toDateString(),
        ]);

        $resolver = new AuthorizationResolver;
        $this->assertFalse($resolver->check($user, 'users.view'));
    }

    public function test_revoked_role_is_denied()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();
        ActiveTenantContext::set($tenant->id);

        $membership = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'type' => RoleType::TENANT_CUSTOM]);
        Permission::create(['key' => 'users.view']);
        AuthorizationGrant::create(['role_id' => $role->id, 'permission_key' => 'users.view', 'scope_type' => AuthScope::TENANT]);

        $assignment = MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'effective_from' => now()->subDays(10)->toDateString(),
            'revoked_at' => now(),
        ]);

        $resolver = new AuthorizationResolver;
        $this->assertFalse($resolver->check($user, 'users.view'));
    }
}
