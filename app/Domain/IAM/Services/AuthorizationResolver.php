<?php

namespace App\Domain\IAM\Services;

use App\Domain\IAM\Enums\MembershipStatus;
use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\Permission;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\Tenant\ActiveTenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AuthorizationResolver
{
    /**
     * Check if a permission is managed by the IAM registry.
     */
    public function isAuthoritative(string $permissionKey): bool
    {
        return Cache::remember("iam_permission_exists_{$permissionKey}", 3600, function () use ($permissionKey) {
            return Permission::where('key', $permissionKey)->exists();
        });
    }

    /**
     * Determine if the user has the given permission within the current active tenant context.
     * Optionally passing a scope type and ID to validate.
     */
    public function check(User $user, string $permissionKey, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        if (! ActiveTenantContext::isSet()) {
            return false; // All auth must happen within a resolved tenant context
        }

        $tenantId = ActiveTenantContext::get();

        // 1. Get the user's active membership for this tenant
        $membership = TenantMembership::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', MembershipStatus::ACTIVE)
            ->first();

        if (! $membership) {
            return false;
        }

        // Cache key logic goes here (simplified for this foundation)
        // W1A.10 cache integration
        $now = now();
        $hasPermission = false;

        // 2. Load valid roles assigned to this membership
        $roles = $membership->membershipRoles()
            ->whereNull('revoked_at')
            ->whereDate('effective_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $now);
            })
            ->with(['role.grants' => function ($q) use ($permissionKey) {
                $q->where('permission_key', $permissionKey)
                    ->whereNull('revoked_at');
            }])
            ->get()
            ->pluck('role');

        foreach ($roles as $role) {
            // Defense in depth: Tenant Custom/System roles must belong to the active tenant
            if ($role->type !== RoleType::PLATFORM_SYSTEM && $role->tenant_id !== $tenantId) {
                continue; // Prevent cross-tenant role bleed
            }

            foreach ($role->grants as $grant) {
                // If a specific scope is requested (e.g. checking if they can edit THIS branch)
                if ($scopeType) {
                    if ($grant->scope_type->value === $scopeType) {
                        if ($grant->scope_type->requiresScopeId() && $grant->scope_id !== $scopeId) {
                            continue; // Scope mismatch
                        }

                        return true; // Match found
                    }
                } else {
                    // Just checking if they have the permission globally within the tenant
                    if ($grant->scope_type->value === 'PLATFORM' || $grant->scope_type->value === 'TENANT') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
