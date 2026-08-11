<?php

namespace Tests\Unit\IAM;

use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\IAM\Services\MembershipRoleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_membership_cannot_be_assigned_role_from_another_tenant()
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);

        $user = User::factory()->create();

        $membershipA = TenantMembership::create([
            'tenant_id' => $tenantA->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $roleB = Role::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Admin',
            'type' => RoleType::TENANT_CUSTOM,
        ]);

        $service = new MembershipRoleService;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-tenant role assignment denied');

        $service->assignRole($membershipA, $roleB);
    }

    public function test_platform_system_role_can_be_assigned_across_tenants()
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $user = User::factory()->create();

        $membershipA = TenantMembership::create([
            'tenant_id' => $tenantA->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $platformRole = Role::create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'type' => RoleType::PLATFORM_SYSTEM,
        ]);

        $service = new MembershipRoleService;
        $assignment = $service->assignRole($membershipA, $platformRole);

        $this->assertNotNull($assignment);
        $this->assertEquals($platformRole->id, $assignment->role_id);
    }
}
