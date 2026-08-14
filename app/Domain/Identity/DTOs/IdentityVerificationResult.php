<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use App\Domain\Identity\Enums\IdentityVerificationProviderStatus;

readonly class IdentityVerificationResult
{
    public function __construct(
        public IdentityVerificationProviderStatus $status,
        public ?string $failureCode = null,
        public ?array $verifiedMetadata = null
    ) {}
}
