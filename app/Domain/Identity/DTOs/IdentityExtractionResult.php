<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use App\Domain\Identity\Enums\IdentityExtractionProviderStatus;

readonly class IdentityExtractionResult
{
    public function __construct(
        public IdentityExtractionProviderStatus $status,
        public ?string $name = null,
        public ?string $dob = null,
        public ?string $nidNumber = null,
        public ?float $confidence = null,
        public ?string $failureCode = null,
        public ?array $rawPayload = null
    ) {}
}
