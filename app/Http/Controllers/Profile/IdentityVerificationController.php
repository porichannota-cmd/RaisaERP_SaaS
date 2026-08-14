<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Domain\Identity\Enums\IdentityVerificationStatus;
use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Http\Controllers\Controller;
use App\Models\UserIdentityVerification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IdentityVerificationController extends Controller
{
    /**
     * Retrieve the current identity verification status for the user.
     */
    public function show(Request $request)
    {
        $verification = UserIdentityVerification::where('user_id', $request->user()->id)->first();

        // Safe status mapping
        $status = $verification ? $verification->status->value : IdentityVerificationStatus::NOT_STARTED->value;
        $maskedNid = null;

        if ($verification && $verification->nid_number_encrypted) {
            // Decrypting just to mask it in this foundation phase.
            // A more robust masking service could be used.
            $cipher = app(SensitiveDataCipherInterface::class);
            try {
                $plaintext = $cipher->decrypt($verification->nid_number_encrypted);
                $maskedNid = '********'.substr($plaintext, -4);
            } catch (\Exception $e) {
                $maskedNid = '********ERROR';
            }
        }

        return Inertia::render('Profile/Identity/Show', [
            'status' => $status,
            'maskedNid' => $maskedNid,
            'lastAttemptAt' => $verification?->last_attempt_at?->toIso8601String(),
            'verifiedAt' => $verification?->verified_at?->toIso8601String(),
            'manualReviewRequired' => $verification?->manual_review_required ?? false,
        ]);
    }

    /**
     * Trigger extraction manually (for testing UI flow).
     */
    public function extract(Request $request)
    {
        // This is a stub for future direct-trigger if permitted.
        // In reality, extraction might happen asynchronously when a document is uploaded.
        // Or it could be synchronous here.
        abort(501, 'Extraction trigger not implemented for direct API yet.');
    }

    /**
     * Trigger verification manually.
     */
    public function verify(Request $request)
    {
        // For testing the null provider flow
        abort(501, 'Verification trigger not implemented for direct API yet.');
    }
}
