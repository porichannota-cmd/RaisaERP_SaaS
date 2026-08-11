<?php

namespace Tests\Unit\IAM;

use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_system_role_cannot_have_tenant_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MUST have a null tenant_id');

        Role::create([
            'tenant_id' => 'some-tenant-id',
            'type' => RoleType::PLATFORM_SYSTEM,
            'name' => 'Invalid Platform Role',
        ]);
    }

    public function test_tenant_custom_role_must_have_tenant_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MUST have a non-null tenant_id');

        Role::create([
            'tenant_id' => null,
            'type' => RoleType::TENANT_CUSTOM,
            'name' => 'Invalid Tenant Role',
        ]);
    }

    public function test_valid_roles_can_be_created()
    {
        $platformRole = Role::create([
            'tenant_id' => null,
            'type' => RoleType::PLATFORM_SYSTEM,
            'name' => 'Super Admin',
        ]);
        $this->assertNotNull($platformRole->id);

        $tenant = Tenant::create(['name' => 'Test Tenant']);

        $tenantRole = Role::create([
            'tenant_id' => $tenant->id,
            'type' => RoleType::TENANT_CUSTOM,
            'name' => 'Tenant Admin',
        ]);
        $this->assertNotNull($tenantRole->id);
    }
}
