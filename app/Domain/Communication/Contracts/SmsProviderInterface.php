<?php

namespace App\Domain\Communication\Contracts;

use App\Domain\Communication\DTOs\DeliveryResult;
use App\Domain\Communication\DTOs\SmsMessage;

interface SmsProviderInterface
{
    /**
     * Send an SMS message.
     * Must not throw on provider errors; return a DeliveryResult instead.
     */
    public function send(SmsMessage $message): DeliveryResult;

    /**
     * A human-readable name for this provider (for logging/audit, not credentials).
     */
    public function providerName(): string;
}
