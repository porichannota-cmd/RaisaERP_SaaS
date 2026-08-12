<?php

namespace App\Domain\Communication\Contracts;

use App\Domain\Communication\DTOs\DeliveryResult;

interface EmailProviderInterface
{
    /**
     * Send an OTP email.
     * The $to email address should already be normalized.
     * The $subject and $body are pre-composed by the calling service.
     * Must not throw on provider errors; return DeliveryResult instead.
     */
    public function send(string $to, string $subject, string $body): DeliveryResult;

    /**
     * A human-readable name for this provider.
     */
    public function providerName(): string;
}
