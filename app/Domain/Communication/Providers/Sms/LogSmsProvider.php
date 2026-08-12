<?php

namespace App\Domain\Communication\Providers\Sms;

use App\Domain\Communication\Contracts\SmsProviderInterface;
use App\Domain\Communication\DTOs\DeliveryResult;
use App\Domain\Communication\DTOs\SmsMessage;
use App\Domain\Communication\Services\DestinationNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Log SMS Provider — safe for development and CI.
 *
 * SECURITY INVARIANT: In production, the OTP code is NOT written to application logs.
 * Only the masked destination and purpose are logged.
 * The config/otp.php 'production_log_provider_guard' must be true in production
 * to prevent this provider from being loaded as the default.
 */
class LogSmsProvider implements SmsProviderInterface
{
    public function __construct(
        private readonly DestinationNormalizer $normalizer,
    ) {}

    public function send(SmsMessage $message): DeliveryResult
    {
        $maskedDestination = $this->normalizer->maskMobile($message->destination);

        Log::channel('stack')->info('OTP SMS [LogProvider]', [
            'destination' => $maskedDestination,
            'purpose' => $message->purpose->value,
            'correlation' => $message->correlationId,
            // body is logged here intentionally for local dev/test ONLY
            // Production guard prevents this provider from being active in prod
            'body' => app()->environment('production') ? '[REDACTED]' : $message->body,
        ]);

        return DeliveryResult::success($this->providerName(), 'log-'.uniqid());
    }

    public function providerName(): string
    {
        return 'log';
    }
}
