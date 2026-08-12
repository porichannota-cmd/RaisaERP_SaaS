<?php

namespace App\Domain\Communication\DTOs;

/**
 * Canonical provider delivery result.
 * Never exposes provider secrets or internal exception traces.
 */
final readonly class DeliveryResult
{
    public function __construct(
        public bool $accepted,
        public string $provider,
        public ?string $providerMessageId = null,
        public string $status = 'unknown',
        public ?string $errorCode = null,
        public bool $retryable = false,
        public array $metadata = [],
    ) {}

    public static function success(string $provider, ?string $messageId = null): self
    {
        return new self(
            accepted: true,
            provider: $provider,
            providerMessageId: $messageId,
            status: 'accepted',
        );
    }

    public static function temporaryFailure(string $provider, string $errorCode = ''): self
    {
        return new self(
            accepted: false,
            provider: $provider,
            status: 'temporary_failure',
            errorCode: $errorCode,
            retryable: true,
        );
    }

    public static function permanentFailure(string $provider, string $errorCode = ''): self
    {
        return new self(
            accepted: false,
            provider: $provider,
            status: 'permanent_failure',
            errorCode: $errorCode,
            retryable: false,
        );
    }

    public static function providerUnavailable(string $provider): self
    {
        return new self(
            accepted: false,
            provider: $provider,
            status: 'provider_unavailable',
            retryable: true,
        );
    }
}
