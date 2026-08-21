<?php

namespace Tests\Feature\Business;

use App\Domain\IAM\Enums\MembershipStatus;
use App\Domain\IAM\Models\MembershipRole;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithAdmin(User $user, string $name = 'Test Business'): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name,
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
            'scope_type' => 'TENANT',
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id' => $adminRole->id,
            'effective_from' => now(),
        ]);

        return $tenant;
    }

    public function test_dashboard_payload_contains_safe_tenant_and_membership_data()
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantWithAdmin($user, 'Synthetic Alpha');

        $this->actingAs($user)
             ->withSession(['active_tenant_id' => $tenant->id]);

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('dashboardData', fn (Assert $data) => $data
                ->has('workspace', fn (Assert $workspace) => $workspace
                    ->where('id', $tenant->id)
                    ->where('name', 'Synthetic Alpha')
                    ->where('status', 'active')
                )
                ->has('membership', fn (Assert $membership) => $membership
                    ->where('status', 'active')
                    ->where('access_level', 'Tenant Admin')
                )
                ->has('account', fn (Assert $account) => $account
                    ->where('name', $user->name)
                    ->has('status')
                )
            )
        );
    }

    public function test_tenant_a_data_does_not_expose_tenant_b()
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantWithAdmin($user, 'Tenant A');
        $tenantB = $this->createTenantWithAdmin($user, 'Tenant B');

        $this->actingAs($user)
             ->withSession(['active_tenant_id' => $tenantA->id]);

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('dashboardData', fn (Assert $data) => $data
                ->where('workspace.id', $tenantA->id)
                ->where('workspace.name', 'Tenant A')
                ->etc()
            )
        );
    }
}
