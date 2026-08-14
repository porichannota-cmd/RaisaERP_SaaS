<?php

declare(strict_types=1);

namespace App\Domain\Business\Services;

use App\Domain\Business\Enums\ProvisioningStatus;
use App\Domain\Business\Models\BusinessProfile;
use App\Domain\IAM\Enums\MembershipStatus;
use App\Domain\IAM\Models\MembershipRole;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\IAM\Services\TenantIamBootstrapper;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantProvisioningService
{
    public function __construct(private TenantIamBootstrapper $iamBootstrapper)
    {
    }

    public function provision(BusinessProfile $profile, User $actor): Tenant
    {
        return DB::transaction(function () use ($profile, $actor) {
            // Lock the profile for update
            $profile = BusinessProfile::lockForUpdate()->findOrFail($profile->id);

            // Verify owner
            if ($profile->owner_user_id !== $actor->id) {
                throw new \RuntimeException('Unauthorized provisioning attempt.');
            }

            // Verify AccountStatus ACTIVE
            if ($actor->account_status->value !== 'active') {
                throw new \RuntimeException('Owner account must be ACTIVE to provision a tenant.');
            }

            // Return existing Tenant if already provisioned (Idempotency)
            if ($profile->provisioning_status === ProvisioningStatus::PROVISIONED && $profile->tenant_id) {
                return $profile->tenant;
            }

            // Verify readiness
            if ($profile->provisioning_status !== ProvisioningStatus::READY_FOR_PROVISIONING) {
                throw new \RuntimeException('Business profile is not ready for provisioning.');
            }

            // 1. Create Tenant
            $tenant = Tenant::create([
                'name' => $profile->legal_name,
                'status' => 'active',
            ]);

            // 2. Call TenantIamBootstrapper
            $tenantAdminRole = $this->iamBootstrapper->bootstrapForTenant($tenant);

            // 3. Create TenantMembership for owner
            $membership = TenantMembership::firstOrCreate([
                'tenant_id' => $tenant->id,
                'user_id' => $actor->id,
            ], [
                'status' => MembershipStatus::ACTIVE,
            ]);

            // 4. Associate canonical TENANT_ADMIN role
            MembershipRole::firstOrCreate([
                'membership_id' => $membership->id,
                'role_id' => $tenantAdminRole->id,
            ], [
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'effective_from' => now()->toDateString(),
            ]);

            // 5. Link business_profile.tenant_id
            $profile->tenant_id = $tenant->id;
            $profile->provisioning_status = ProvisioningStatus::PROVISIONED;
            $profile->save();

            return $tenant;
        });
    }
}
