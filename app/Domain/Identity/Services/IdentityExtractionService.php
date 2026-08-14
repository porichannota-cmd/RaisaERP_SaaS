<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\IdentityDocumentExtractionInterface;
use App\Domain\Identity\DTOs\IdentityExtractionResult;
use App\Domain\Identity\Enums\IdentityExtractionProviderStatus;
use App\Domain\Identity\Enums\IdentityVerificationOperation;
use App\Domain\Identity\Enums\IdentityVerificationStatus;
use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Domain\Registration\Contracts\SensitiveLookupHasherInterface;
use App\Models\IdentityVerificationAttempt;
use App\Models\RegistrationIdentityDocument;
use App\Models\User;
use App\Models\UserIdentityVerification;
use Illuminate\Support\Facades\DB;

class IdentityExtractionService
{
    public function __construct(
        private readonly IdentityDocumentExtractionInterface $extractionProvider,
        private readonly SensitiveDataCipherInterface $cipher,
        private readonly SensitiveLookupHasherInterface $hasher
    ) {}

    public function extractIdentityData(User $user, RegistrationIdentityDocument $front, ?RegistrationIdentityDocument $back = null): IdentityExtractionResult
    {
        // Check ownership
        if ($front->claimed_by_user_id !== $user->id) {
            throw new \RuntimeException('Document does not belong to user.');
        }

        if ($back !== null && $back->claimed_by_user_id !== $user->id) {
            throw new \RuntimeException('Back document does not belong to user.');
        }

        // Provider string representation
        $providerName = config('identity.extraction_provider', 'null');

        // Create or get the verification record
        $verification = UserIdentityVerification::firstOrCreate(
            ['user_id' => $user->id],
            [
                'document_type' => 'nid',
                'status' => IdentityVerificationStatus::NOT_STARTED,
                'provider' => $providerName,
                'manual_review_required' => false,
            ]
        );

        $attempt = IdentityVerificationAttempt::create([
            'user_identity_verification_id' => $verification->id,
            'user_id' => $user->id,
            'provider' => $providerName,
            'operation' => IdentityVerificationOperation::EXTRACT->value,
            'status' => 'PENDING',
            'started_at' => now(),
        ]);

        try {
            DB::beginTransaction();

            $result = $this->extractionProvider->extract($front, $back);

            $attempt->update([
                'status' => $result->status->value,
                'failure_code' => $result->failureCode,
                'completed_at' => now(),
                'metadata' => $result->rawPayload,
            ]);

            if ($result->status === IdentityExtractionProviderStatus::SUCCESS) {
                $verification->update([
                    'status' => IdentityVerificationStatus::EXTRACTED,
                    'normalized_name' => $result->name,
                    'normalized_dob' => $result->dob,
                    'nid_number_encrypted' => $result->nidNumber ? $this->cipher->encrypt($result->nidNumber) : null,
                    'nid_number_fingerprint' => $result->nidNumber ? $this->hasher->hash($result->nidNumber) : null,
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
                'status' => IdentityExtractionProviderStatus::PROVIDER_ERROR->value,
                'failure_code' => 'EXCEPTION',
                'completed_at' => now(),
                'metadata' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }
}
