<?php

declare(strict_types=1);

namespace App\Domain\Identity\Providers;

use App\Domain\Identity\Contracts\IdentityVerificationProviderInterface;
use App\Domain\Identity\DTOs\IdentityVerificationResult;
use App\Domain\Identity\Enums\IdentityVerificationProviderStatus;

class NullIdentityVerificationProvider implements IdentityVerificationProviderInterface
{
    public function verify(string $normalizedName, string $normalizedDob, string $plaintextNid): IdentityVerificationResult
    {
        return new IdentityVerificationResult(
            status: IdentityVerificationProviderStatus::MANUAL_REVIEW_REQUIRED,
            failureCode: 'NULL_PROVIDER_ACTIVE'
        );
    }
}
