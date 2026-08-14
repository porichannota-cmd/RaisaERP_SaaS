<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Services;

use App\Domain\IAM\Enums\MembershipStatus;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\IAM\Services\AuthorizationResolver;
use App\Domain\Tenant\ActiveTenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class WorkspaceContextService
{
    private const SESSION_KEY = 'active_tenant_id';

    public function __construct(
        private readonly AuthorizationResolver $authorizationResolver
    ) {
    }

    /**
     * Lists tenants where the user has an ACTIVE membership.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Tenant>
     */
    public function listAvailableWorkspaces(User $user)
    {
        return Tenant::whereHas('memberships', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('status', MembershipStatus::ACTIVE);
        })->get();
    }

    /**
     * Attempts to switch the active workspace to the provided tenant ID.
     * Validates membership and IAM before setting the session.
     */
    public function switchWorkspace(User $user, string $tenantId): bool
    {
        // 1. Verify Tenant exists and User has an ACTIVE membership
        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::ACTIVE)
            ->first();

        if (! $membership) {
            return false;
        }

        // 2. Mock establishing context temporarily to resolve IAM
        ActiveTenantContext::set($tenantId);

        // 3. Resolve IAM permission
        $hasAccess = $this->authorizationResolver->check($user, 'tenant.workspace.access');

        // Clean up temporary context
        ActiveTenantContext::clear();

        if (! $hasAccess) {
            return false;
        }

        // 4. Set session
        Session::put(self::SESSION_KEY, $tenantId);

        return true;
    }

    /**
     * Explicitly clears the workspace session context.
     */
    public function clearWorkspace(): void
    {
        Session::forget(self::SESSION_KEY);
        ActiveTenantContext::clear();
    }

    /**
     * Safely reads and revalidates the session context per request.
     * Returns the Tenant if valid, otherwise null (and clears session).
     */
    public function resolveActiveWorkspace(User $user): ?Tenant
    {
        $tenantId = Session::get(self::SESSION_KEY);

        if (! $tenantId) {
            return null;
        }

        // Validate membership still exists and is ACTIVE
        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::ACTIVE)
            ->first();

        if (! $membership) {
            $this->clearWorkspace();
            return null;
        }

        // Set context to resolve IAM
        ActiveTenantContext::set($tenantId);

        if (! $this->authorizationResolver->check($user, 'tenant.workspace.access')) {
            $this->clearWorkspace();
            return null;
        }

        return $membership->tenant;
    }
}
