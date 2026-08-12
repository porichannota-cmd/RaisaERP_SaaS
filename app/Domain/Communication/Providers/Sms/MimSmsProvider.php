<?php

namespace App\Domain\Communication\Providers\Sms;

use App\Domain\Communication\Contracts\SmsProviderInterface;
use App\Domain\Communication\DTOs\DeliveryResult;
use App\Domain\Communication\DTOs\SmsMessage;

/**
 * MIM SMS Provider — configuration boundary skeleton.
 *
 * STATUS: MIM SMS LIVE INTEGRATION = CONFIGURATION PENDING
 *
 * This adapter boundary exists and follows the canonical SmsProviderInterface.
 * Live integration requires authoritative credentials and API contract from MIM SMS.
 *
 * Required environment variables (NOT committed):
 *   MIM_SMS_API_URL
 *   MIM_SMS_USERNAME
 *   MIM_SMS_PASSWORD
 *   MIM_SMS_SENDER_ID
 */
class MimSmsProvider implements SmsProviderInterface
{
    public function __construct(
        private readonly ?string $apiUrl,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly ?string $senderId,
    ) {}

    public function send(SmsMessage $message): DeliveryResult
    {
        if (! $this->isConfigured()) {
            return DeliveryResult::providerUnavailable($this->providerName());
        }

        // TODO: Implement live MIM SMS HTTP integration when credentials are available.
        // The implementation must:
        //   1. POST to $this->apiUrl with authentication
        //   2. Map provider response to DeliveryResult
        //   3. Never log $message->body in production
        //   4. Never expose credentials in error messages
        return DeliveryResult::providerUnavailable($this->providerName());
    }

    public function providerName(): string
    {
        return 'mim';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiUrl)
            && ! empty($this->username)
            && ! empty($this->password);
    }
}
