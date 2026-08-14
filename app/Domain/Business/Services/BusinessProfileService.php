<?php

declare(strict_types=1);

namespace App\Domain\Business\Services;

use App\Domain\Business\Enums\ProvisioningStatus;
use App\Domain\Business\Models\BusinessAddress;
use App\Domain\Business\Models\BusinessProfile;
use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Domain\Registration\Contracts\SensitiveLookupHasherInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BusinessProfileService
{
    public function __construct(
        private SensitiveDataCipherInterface $cipher,
        private SensitiveLookupHasherInterface $hasher
    ) {
    }

    public function createOrUpdateProfile(User $owner, array $data): BusinessProfile
    {
        return DB::transaction(function () use ($owner, $data) {
            $profile = BusinessProfile::where('owner_user_id', $owner->id)->first();
            if (!$profile) {
                $profile = new BusinessProfile();
                $profile->owner_user_id = $owner->id;
            }

            if ($profile->provisioning_status === ProvisioningStatus::PROVISIONED) {
                // If already provisioned, only allow updates to specific fields if necessary,
                // or just allow updating legal_name/display_name.
                $profile->legal_name = $data['legal_name'] ?? $profile->legal_name;
                $profile->display_name = $data['display_name'] ?? $profile->display_name;
            } else {
                $profile->legal_name = $data['legal_name'] ?? $profile->legal_name;
                $profile->display_name = $data['display_name'] ?? $profile->display_name;
            }

            if (isset($data['trade_license'])) {
                $profile->trade_license_encrypted = $this->cipher->encrypt($data['trade_license']);
                $profile->trade_license_fingerprint = $this->hasher->hash($data['trade_license']);
            }

            if (isset($data['tin'])) {
                $profile->tin_encrypted = $this->cipher->encrypt($data['tin']);
                $profile->tin_fingerprint = $this->hasher->hash($data['tin']);
            }

            if (isset($data['bin'])) {
                $profile->bin_encrypted = $this->cipher->encrypt($data['bin']);
                $profile->bin_fingerprint = $this->hasher->hash($data['bin']);
            }

            $profile->save();

            return $profile;
        });
    }

    public function createOrUpdateAddress(BusinessProfile $profile, array $data): BusinessAddress
    {
        return DB::transaction(function () use ($profile, $data) {
            $address = clone ($profile->address ?? new BusinessAddress());
            if (!$address->exists) {
                $address->business_profile_id = $profile->id;
            }
            $address->fill([
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $data['address_line_2'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'],
                'country' => $data['country'] ?? 'BD',
            ]);
            
            $address->save();
            
            return $address;
        });
    }

    public function evaluateReadiness(BusinessProfile $profile): BusinessProfile
    {
        return DB::transaction(function () use ($profile) {
            // Re-fetch to ensure fresh data and prevent race conditions within readiness logic
            // though not strictly locked here.
            $profile->refresh();

            if ($profile->provisioning_status === ProvisioningStatus::PROVISIONED) {
                return $profile;
            }

            $owner = $profile->owner;

            if ($owner->account_status->value !== 'active') {
                return $profile;
            }

            if (empty($profile->legal_name)) {
                return $profile;
            }

            if (!$profile->address) {
                return $profile;
            }

            $profile->provisioning_status = ProvisioningStatus::READY_FOR_PROVISIONING;
            $profile->save();

            return $profile;
        });
    }
}
