<?php

namespace App\Domain\IAM\Services;

use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\MembershipRole;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\TenantMembership;

class MembershipRoleService
{
    public function assignRole(TenantMembership $membership, Role $role, ?int $assignedBy = null): MembershipRole
    {
        // 1. Cross-Tenant Security Guard
        if ($role->type !== RoleType::PLATFORM_SYSTEM) {
            if ($membership->tenant_id !== $role->tenant_id) {
                throw new \InvalidArgumentException('Cross-tenant role assignment denied: Membership and Role tenants do not match.');
            }
        }

        // 2. Platform protection: Only platform admins can assign platform system roles.
        // Assuming we have a check for the assigner having 'roles.assign_platform' permission.
        // That is enforced via Gate before calling this service.

        return MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'assigned_by' => $assignedBy,
            'effective_from' => now()->toDateString(),
            // Audit event is automatically logged via Auditable trait
        ]);
    }

    public function revokeRole(MembershipRole $assignment, ?int $revokedBy = null): void
    {
        $assignment->update([
            'revoked_at' => now(),
            'revoked_by' => $revokedBy,
            'effective_until' => now()->toDateString(),
        ]);
        // Audit event automatically logged
        // W1A.10: Cache invalidation would happen here
    }
}
