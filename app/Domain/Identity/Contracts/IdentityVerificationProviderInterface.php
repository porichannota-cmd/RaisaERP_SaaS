<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\DTOs\IdentityVerificationResult;

interface IdentityVerificationProviderInterface
{
    /**
     * Verify the normalized identity payload against an authoritative source.
     *
     * @param  string  $normalizedDob  (YYYY-MM-DD)
     */
    public function verify(string $normalizedName, string $normalizedDob, string $plaintextNid): IdentityVerificationResult;
}
