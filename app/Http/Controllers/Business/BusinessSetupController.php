<?php

declare(strict_types=1);

namespace App\Http\Controllers\Business;

use App\Domain\Business\Models\BusinessProfile;
use App\Domain\Business\Services\BusinessProfileService;
use App\Domain\Business\Services\TenantProvisioningService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\UpdateBusinessAddressRequest;
use App\Http\Requests\Business\UpdateBusinessProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSetupController extends Controller
{
    public function __construct(
        private BusinessProfileService $profileService,
        private TenantProvisioningService $provisioningService
    ) {
    }

    public function show(Request $request): Response
    {
        $owner = $request->user();
        $profile = BusinessProfile::with('address')->where('owner_user_id', $owner->id)->first();

        // Need to hide encrypted data if present
        if ($profile) {
            $profile->makeHidden([
                'trade_license_encrypted',
                'trade_license_fingerprint',
                'tin_encrypted',
                'tin_fingerprint',
                'bin_encrypted',
                'bin_fingerprint',
            ]);
        }

        return Inertia::render('Business/Setup', [
            'profile' => $profile,
            'is_ready' => $profile && $profile->provisioning_status->value === 'READY_FOR_PROVISIONING',
            'is_provisioned' => $profile && $profile->provisioning_status->value === 'PROVISIONED',
        ]);
    }

    public function saveProfile(UpdateBusinessProfileRequest $request): RedirectResponse
    {
        $this->profileService->createOrUpdateProfile($request->user(), $request->validated());

        return redirect()->back()->with('success', 'Business profile saved successfully.');
    }

    public function saveAddress(UpdateBusinessAddressRequest $request): RedirectResponse
    {
        $profile = BusinessProfile::where('owner_user_id', $request->user()->id)->firstOrFail();
        
        $this->profileService->createOrUpdateAddress($profile, $request->validated());

        return redirect()->back()->with('success', 'Business address saved successfully.');
    }

    public function markReady(Request $request): RedirectResponse
    {
        $profile = BusinessProfile::where('owner_user_id', $request->user()->id)->firstOrFail();
        
        $this->profileService->evaluateReadiness($profile);

        return redirect()->back()->with('success', 'Business readiness evaluated.');
    }

    public function provision(Request $request): RedirectResponse
    {
        $profile = BusinessProfile::where('owner_user_id', $request->user()->id)->firstOrFail();
        
        $tenant = $this->provisioningService->provision($profile, $request->user());

        return redirect()->route('dashboard')->with('success', "Workspace provisioned for {$tenant->name}.");
    }
}
