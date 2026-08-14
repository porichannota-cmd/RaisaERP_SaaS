<?php

declare(strict_types=1);

namespace App\Domain\IAM\Services;

use App\Domain\IAM\Enums\AuthScope;
use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\AuthorizationGrant;
use App\Domain\IAM\Models\Permission;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\Tenant;

class TenantIamBootstrapper
{
    public const CANONICAL_TENANT_ADMIN_CODE = 'TENANT_ADMIN';

    /**
     * The minimum explicit canonical permission set needed for a newly
     * provisioned Tenant Admin to bootstrap and manage their workspace.
     */
    public const MINIMUM_TENANT_ADMIN_PERMISSIONS = [
        'tenant.workspace.access' => 'Access and enter the tenant workspace',
        'tenant.settings.view' => 'View basic tenant settings and identity',
        'tenant.memberships.view' => 'View tenant memberships',
        'tenant.memberships.manage' => 'Manage tenant memberships',
    ];

    /**
     * Bootstraps the foundational IAM architecture for a specific tenant.
     * Ensures the canonical Tenant Admin role exists idempotently.
     */
    public function bootstrapForTenant(Tenant $tenant): Role
    {
        // 1. Ensure minimum canonical permissions exist globally.
        $this->bootstrapCanonicalPermissions();

        // 2. Ensure canonical role exists for this tenant.
        $role = Role::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'code' => self::CANONICAL_TENANT_ADMIN_CODE,
                'type' => RoleType::TENANT_SYSTEM,
            ],
            [
                'name' => 'Tenant Admin',
                'is_system' => true,
            ]
        );

        // 3. Ensure AuthorizationGrants exist linking the role to the permissions.
        $this->bootstrapCanonicalGrants($role);

        return $role;
    }

    private function bootstrapCanonicalPermissions(): void
    {
        foreach (self::MINIMUM_TENANT_ADMIN_PERMISSIONS as $key => $description) {
            Permission::firstOrCreate(
                ['key' => $key],
                ['description' => $description]
            );
        }
    }

    private function bootstrapCanonicalGrants(Role $role): void
    {
        foreach (array_keys(self::MINIMUM_TENANT_ADMIN_PERMISSIONS) as $key) {
            AuthorizationGrant::firstOrCreate(
                [
                    'role_id' => $role->id,
                    'permission_key' => $key,
                    'scope_type' => AuthScope::TENANT,
                    'scope_id' => null, // TENANT scope natively relies on ActiveTenantContext
                ]
            );
        }
    }

    /**
     * Resolves the canonical Tenant Admin role for a specific tenant.
     * Fails closed if the role does not exist (meaning the tenant was not bootstrapped).
     */
    public function resolveTenantAdminRole(Tenant $tenant): Role
    {
        $role = Role::where('tenant_id', $tenant->id)
            ->where('code', self::CANONICAL_TENANT_ADMIN_CODE)
            ->where('type', RoleType::TENANT_SYSTEM)
            ->where('is_system', true)
            ->first();

        if (! $role) {
            throw new \RuntimeException("Canonical Tenant Admin role not found for tenant: {$tenant->id}. Tenant IAM bootstrap is missing or corrupt.");
        }

        return $role;
    }
}
