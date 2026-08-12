<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Registration\Enums\RegistrationDocumentStatus;
use App\Models\RegistrationIdentityDocument;
use App\Models\RegistrationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrationIdentityDocumentClaimService
{
    /**
     * Claims all pending identity documents for a completed registration session.
     * This safely binds the pre-user staging evidence to the newly created user account.
     * Note: This does NOT convert them to tenant MediaAssets, as tenant provisioning
     * happens in a later lifecycle stage.
     */
    public function claimDocumentsForUser(RegistrationSession $session, User $user): void
    {
        try {
            DB::transaction(function () use ($session, $user) {
                RegistrationIdentityDocument::where('registration_session_id', $session->id)
                    ->whereIn('status', [
                        RegistrationDocumentStatus::PENDING->value,
                        RegistrationDocumentStatus::UPLOADED->value,
                        RegistrationDocumentStatus::VALIDATED->value,
                    ])
                    ->update([
                        'status' => RegistrationDocumentStatus::CLAIMED,
                        'claimed_by_user_id' => $user->id,
                        'claimed_at' => now(),
                    ]);
            });
        } catch (\Exception $e) {
            // Claim failure should not typically crash the registration orchestration.
            // Log it as critical for retries, but let the user account creation stand.
            Log::critical('REGISTRATION_DOCUMENT_CLAIM_FAILED', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
