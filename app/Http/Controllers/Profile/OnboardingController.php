<?php

namespace App\Http\Controllers\Profile;

use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use App\Models\UserBankAccount;
use App\Models\UserConsent;
use App\Models\UserIdentityVerification;
use App\Models\UserMfsAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $profile = $user->profile()->first();
        $contact = $user->contactDetail()->first();
        $addresses = UserAddress::where('user_id', $user->id)->get();

        // For Bank and MFS, ensure we mask the encrypted fields before sending to the client
        $bankAccounts = UserBankAccount::where('user_id', $user->id)->get()->map(function ($acc) {
            $data = $acc->toArray();
            unset($data['account_number_encrypted']);
            $data['account_number_masked'] = '****'.substr($acc->account_number_fingerprint, -4);

            return $data;
        });

        $mfsAccounts = UserMfsAccount::where('user_id', $user->id)->get()->map(function ($acc) {
            $data = $acc->toArray();
            unset($data['mobile_encrypted']);
            $data['mobile_masked'] = '****'.substr($acc->mobile_fingerprint, -4);

            return $data;
        });

        $consents = UserConsent::where('user_id', $user->id)->get();
        $sectionStatuses = $user->sectionStatuses()->get();

        $verification = UserIdentityVerification::where('user_id', $user->id)->first();
        $identityStatus = [
            'status' => $verification ? $verification->status->value : 'NOT_STARTED',
            'manualReviewRequired' => $verification ? $verification->manual_review_required : false,
            'maskedNid' => null,
        ];

        if ($verification && $verification->nid_number_encrypted) {
            try {
                $cipher = app(SensitiveDataCipherInterface::class);
                $plaintext = $cipher->decrypt($verification->nid_number_encrypted);
                $identityStatus['maskedNid'] = '********'.substr($plaintext, -4);
            } catch (\Exception $e) {
                $identityStatus['maskedNid'] = '********ERROR';
            }
        }

        return Inertia::render('profile/index', [
            'profile' => $profile,
            'contact' => $contact,
            'addresses' => $addresses,
            'bankAccounts' => $bankAccounts,
            'mfsAccounts' => $mfsAccounts,
            'consents' => $consents,
            'sectionStatuses' => $sectionStatuses,
            'identityStatus' => $identityStatus,
        ]);
    }
}
