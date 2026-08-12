<?php

namespace App\Domain\Communication\DTOs;

use App\Domain\Communication\Enums\OtpPurpose;

/**
 * Immutable SMS message DTO.
 * Never inject raw OTP code into this object for logging purposes;
 * the body is assembled from a template reference in providers.
 */
final readonly class SmsMessage
{
    public function __construct(
        public string $destination,
        public string $body,
        public OtpPurpose $purpose,
        public string $correlationId,
        public ?string $tenantId = null,
        public array $metadata = [],
    ) {}
}
