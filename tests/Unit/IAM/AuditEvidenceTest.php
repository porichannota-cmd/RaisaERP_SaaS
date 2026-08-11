<?php

namespace Tests\Unit\IAM;

use App\Domain\Audit\AuditLog;
use App\Domain\IAM\Enums\MembershipStatus;
use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\MembershipRole;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\IAM\Services\MembershipRoleService;
use App\Domain\Tenant\ActiveTenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_lifecycle_creates_structured_audit_logs()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        ActiveTenantContext::set($tenant->id);

        $user = User::factory()->create();

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::PENDING,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TenantMembership::class,
            'auditable_id' => $membership->id,
            'event_type' => 'created',
        ]);

        $membership->update(['status' => MembershipStatus::ACTIVE]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TenantMembership::class,
            'auditable_id' => $membership->id,
            'event_type' => 'updated',
        ]);

        $log = AuditLog::where('auditable_id', $membership->id)
            ->where('event_type', 'updated')
            ->first();

        $this->assertEquals(MembershipStatus::ACTIVE->value, $log->new_values['status']);
    }

    public function test_role_assignment_and_revocation_creates_structured_audit_logs()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        ActiveTenantContext::set($tenant->id);

        $user = User::factory()->create();
        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'type' => RoleType::TENANT_CUSTOM,
        ]);

        $service = new MembershipRoleService;
        $assignment = $service->assignRole($membership, $role);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => MembershipRole::class,
            'auditable_id' => $assignment->id,
            'event_type' => 'created',
        ]);

        $service->revokeRole($assignment);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => MembershipRole::class,
            'auditable_id' => $assignment->id,
            'event_type' => 'updated',
        ]);

        $log = AuditLog::where('auditable_id', $assignment->id)
            ->where('event_type', 'updated')
            ->first();

        $this->assertNotNull($log->new_values['revoked_at']);
    }
}
