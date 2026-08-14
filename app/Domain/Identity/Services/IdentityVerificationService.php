<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\IdentityVerificationProviderInterface;
use App\Domain\Identity\DTOs\IdentityVerificationResult;
use App\Domain\Identity\Enums\IdentityVerificationOperation;
use App\Domain\Identity\Enums\IdentityVerificationProviderStatus;
use App\Domain\Identity\Enums\IdentityVerificationStatus;
use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Models\IdentityVerificationAttempt;
use App\Models\User;
use App\Models\UserIdentityVerification;
use Illuminate\Support\Facades\DB;

class IdentityVerificationService
{
    public function __construct(
        private readonly IdentityVerificationProviderInterface $verificationProvider,
        private readonly SensitiveDataCipherInterface $cipher
    ) {}

    public function verifyIdentity(User $user): IdentityVerificationResult
    {
        $verification = UserIdentityVerification::where('user_id', $user->id)->first();

        if (! $verification) {
            throw new \RuntimeException('No identity data extracted to verify.');
        }

        if ($verification->status === IdentityVerificationStatus::VERIFIED) {
            throw new \RuntimeException('Identity is already verified. Cannot downgrade or re-verify.');
        }

        if (! $verification->normalized_name || ! $verification->normalized_dob || ! $verification->nid_number_encrypted) {
            throw new \RuntimeException('Incomplete extracted data.');
        }

        $plaintextNid = $this->cipher->decrypt($verification->nid_number_encrypted);
        $providerName = config('identity.verification_provider', 'null');

        $attempt = IdentityVerificationAttempt::create([
            'user_identity_verification_id' => $verification->id,
            'user_id' => $user->id,
            'provider' => $providerName,
            'operation' => IdentityVerificationOperation::VERIFY->value,
            'status' => 'PENDING',
            'started_at' => now(),
        ]);

        try {
            DB::beginTransaction();

            $result = $this->verificationProvider->verify(
                $verification->normalized_name,
                $verification->normalized_dob->format('Y-m-d'),
                $plaintextNid
            );

            $attempt->update([
                'status' => $result->status->value,
                'failure_code' => $result->failureCode,
                'completed_at' => now(),
                'metadata' => $result->verifiedMetadata,
            ]);

            if ($result->status === IdentityVerificationProviderStatus::VERIFIED) {
                $verification->update([
                    'status' => IdentityVerificationStatus::VERIFIED,
                    'verified_at' => now(),
                    'last_attempt_at' => now(),
                    'manual_review_required' => false,
                ]);
            } elseif ($result->status === IdentityVerificationProviderStatus::MANUAL_REVIEW_REQUIRED) {
                $verification->update([
                    'status' => IdentityVerificationStatus::MANUAL_REVIEW_REQUIRED,
                    'manual_review_required' => true,
                    'last_attempt_at' => now(),
                ]);
            } else {
                $verification->update([
                    'status' => IdentityVerificationStatus::FAILED,
                    'last_attempt_at' => now(),
                ]);
            }

            DB::commit();

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            $attempt->update([
                'status' => IdentityVerificationProviderStatus::PROVIDER_ERROR->value,
                'failure_code' => 'EXCEPTION',
                'completed_at' => now(),
                'metadata' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }
}
