<?php

namespace Tests\Unit\IAM;

use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Services\TenantIamBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIamBootstrapperTest extends TestCase
{
    use RefreshDatabase;

    private TenantIamBootstrapper $bootstrapper;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapper = new TenantIamBootstrapper();
        $this->tenant = Tenant::create(['name' => 'Acme Corp', 'status' => 'active']);
    }

    public function test_canonical_tenant_admin_bootstrap_succeeds_and_creates_exactly_one_role()
    {
        $this->assertEquals(0, Role::count());

        $role = $this->bootstrapper->bootstrapForTenant($this->tenant);

        $this->assertEquals(1, Role::count());
        $this->assertEquals('Tenant Admin', $role->name);
        $this->assertEquals('TENANT_ADMIN', $role->code);
        $this->assertEquals(RoleType::TENANT_SYSTEM, $role->type);
        $this->assertTrue($role->is_system);
        $this->assertEquals($this->tenant->id, $role->tenant_id);
    }

    public function test_second_bootstrap_remains_idempotent()
    {
        $this->bootstrapper->bootstrapForTenant($this->tenant);
        $this->assertEquals(1, Role::count());

        $role2 = $this->bootstrapper->bootstrapForTenant($this->tenant);
        $this->assertEquals(1, Role::count());

        // Should return the same model
        $this->assertEquals($role2->id, Role::first()->id);
    }

    public function test_stable_canonical_identity_resolves_correctly()
    {
        $this->bootstrapper->bootstrapForTenant($this->tenant);

        $resolvedRole = $this->bootstrapper->resolveTenantAdminRole($this->tenant);

        $this->assertEquals('TENANT_ADMIN', $resolvedRole->code);
        $this->assertEquals($this->tenant->id, $resolvedRole->tenant_id);
    }

    public function test_resolver_fails_closed_if_canonical_role_missing()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Canonical Tenant Admin role not found for tenant: {$this->tenant->id}. Tenant IAM bootstrap is missing or corrupt.");

        $this->bootstrapper->resolveTenantAdminRole($this->tenant);
    }

    public function test_user_defined_role_preserved_and_not_mistaken_for_tenant_admin()
    {
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Custom Role',
            'type' => RoleType::TENANT_CUSTOM,
            'code' => 'CUSTOM_ROLE',
            'is_system' => false,
        ]);

        $this->assertEquals(1, Role::count());

        // Custom role exists, resolver should still fail because canonical doesn't exist
        try {
            $this->bootstrapper->resolveTenantAdminRole($this->tenant);
            $this->fail('Resolver should fail closed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not found', $e->getMessage());
        }

        // Bootstrap should create the new one
        $this->bootstrapper->bootstrapForTenant($this->tenant);
        $this->assertEquals(2, Role::count());

        // Custom role remains untouched
        $customRole = Role::where('name', 'Custom Role')->first();
        $this->assertNotNull($customRole);
        $this->assertFalse($customRole->is_system);
    }

    public function test_no_tenant_created_during_bootstrap()
    {
        $tenantCount = Tenant::count();

        $this->bootstrapper->bootstrapForTenant($this->tenant);

        $this->assertEquals($tenantCount, Tenant::count());
    }

    public function test_cross_tenant_bootstrap_and_resolution_isolation()
    {
        $tenantB = Tenant::create(['name' => 'Global Corp', 'status' => 'active']);

        $roleA = $this->bootstrapper->bootstrapForTenant($this->tenant);
        $roleB = $this->bootstrapper->bootstrapForTenant($tenantB);

        $this->assertEquals(2, Role::count());

        $this->assertNotEquals($roleA->id, $roleB->id);
        $this->assertEquals('TENANT_ADMIN', $roleA->code);
        $this->assertEquals('TENANT_ADMIN', $roleB->code);

        $resolvedRoleA = $this->bootstrapper->resolveTenantAdminRole($this->tenant);
        $resolvedRoleB = $this->bootstrapper->resolveTenantAdminRole($tenantB);

        $this->assertEquals($roleA->id, $resolvedRoleA->id);
        $this->assertEquals($roleB->id, $resolvedRoleB->id);
        $this->assertNotEquals($resolvedRoleA->id, $resolvedRoleB->id);
    }

    public function test_canonical_permissions_and_grants_bootstrap()
    {
        $this->assertEquals(0, \App\Domain\IAM\Models\Permission::count());
        $this->assertEquals(0, \App\Domain\IAM\Models\AuthorizationGrant::count());

        $role = $this->bootstrapper->bootstrapForTenant($this->tenant);

        // Permissions created globally
        $this->assertEquals(count(TenantIamBootstrapper::MINIMUM_TENANT_ADMIN_PERMISSIONS), \App\Domain\IAM\Models\Permission::count());

        // Grants created specific to role
        $this->assertEquals(count(TenantIamBootstrapper::MINIMUM_TENANT_ADMIN_PERMISSIONS), \App\Domain\IAM\Models\AuthorizationGrant::count());

        foreach (array_keys(TenantIamBootstrapper::MINIMUM_TENANT_ADMIN_PERMISSIONS) as $key) {
            $this->assertDatabaseHas('permissions', ['key' => $key]);
            $this->assertDatabaseHas('authorization_grants', [
                'role_id' => $role->id,
                'permission_key' => $key,
                'scope_type' => \App\Domain\IAM\Enums\AuthScope::TENANT,
            ]);
        }
    }

    public function test_idempotency_of_permissions_and_grants()
    {
        $this->bootstrapper->bootstrapForTenant($this->tenant);
        $this->bootstrapper->bootstrapForTenant($this->tenant);

        $this->assertEquals(count(TenantIamBootstrapper::MINIMUM_TENANT_ADMIN_PERMISSIONS), \App\Domain\IAM\Models\Permission::count());
        $this->assertEquals(count(TenantIamBootstrapper::MINIMUM_TENANT_ADMIN_PERMISSIONS), \App\Domain\IAM\Models\AuthorizationGrant::count());
    }

    public function test_custom_permission_preserved()
    {
        \App\Domain\IAM\Models\Permission::create([
            'key' => 'custom.permission',
            'description' => 'Custom'
        ]);

        $this->bootstrapper->bootstrapForTenant($this->tenant);

        $this->assertDatabaseHas('permissions', ['key' => 'custom.permission']);
        $this->assertEquals(count(TenantIamBootstrapper::MINIMUM_TENANT_ADMIN_PERMISSIONS) + 1, \App\Domain\IAM\Models\Permission::count());
    }

    public function test_tenant_admin_role_without_grants_has_no_authority()
    {
        // Create role directly bypassing the bootstrapper (which creates grants)
        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'code' => TenantIamBootstrapper::CANONICAL_TENANT_ADMIN_CODE,
            'type' => RoleType::TENANT_SYSTEM,
            'name' => 'Tenant Admin',
            'is_system' => true,
        ]);

        $user = \App\Models\User::factory()->create();
        \App\Domain\Tenant\ActiveTenantContext::set($this->tenant->id);

        $membership = \App\Domain\IAM\Models\TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        \App\Domain\IAM\Models\MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'effective_from' => now(),
        ]);

        $resolver = new \App\Domain\IAM\Services\AuthorizationResolver();
        $this->assertFalse($resolver->check($user, 'tenant.workspace.access'));
    }

    public function test_bootstrap_grants_enable_intended_authority()
    {
        $role = $this->bootstrapper->bootstrapForTenant($this->tenant);

        $user = \App\Models\User::factory()->create();
        \App\Domain\Tenant\ActiveTenantContext::set($this->tenant->id);

        $membership = \App\Domain\IAM\Models\TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        \App\Domain\IAM\Models\MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'effective_from' => now(),
        ]);

        $resolver = new \App\Domain\IAM\Services\AuthorizationResolver();
        $this->assertTrue($resolver->check($user, 'tenant.workspace.access'));
    }

    public function test_cross_tenant_isolation_of_authority()
    {
        $tenantB = Tenant::create(['name' => 'Tenant B', 'status' => 'active']);
        
        $roleA = $this->bootstrapper->bootstrapForTenant($this->tenant);
        $roleB = $this->bootstrapper->bootstrapForTenant($tenantB);

        $userA = \App\Models\User::factory()->create();

        $membershipA = \App\Domain\IAM\Models\TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);

        \App\Domain\IAM\Models\MembershipRole::create([
            'membership_id' => $membershipA->id,
            'role_id' => $roleA->id,
            'effective_from' => now(),
        ]);

        $resolver = new \App\Domain\IAM\Services\AuthorizationResolver();

        // Check context inside Tenant A
        \App\Domain\Tenant\ActiveTenantContext::set($this->tenant->id);
        $this->assertTrue($resolver->check($userA, 'tenant.workspace.access'));

        // Check context inside Tenant B
        \App\Domain\Tenant\ActiveTenantContext::set($tenantB->id);
        $this->assertFalse($resolver->check($userA, 'tenant.workspace.access'));
    }
}
