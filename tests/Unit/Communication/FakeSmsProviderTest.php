<?php

namespace Tests\Unit\Communication;

use App\Domain\Communication\Contracts\SmsProviderInterface;
use App\Domain\Communication\DTOs\DeliveryResult;
use App\Domain\Communication\DTOs\SmsMessage;
use App\Domain\Communication\Enums\OtpPurpose;
use PHPUnit\Framework\TestCase;

/**
 * Fake SMS provider for unit testing provider contract behavior.
 * Does not invoke real network calls or spend SMS credits.
 */
class FakeSmsProviderTest extends TestCase
{
    public function test_fake_provider_returns_success(): void
    {
        $provider = new class implements SmsProviderInterface
        {
            public function send(SmsMessage $message): DeliveryResult
            {
                return DeliveryResult::success('fake', 'msg-123');
            }

            public function providerName(): string
            {
                return 'fake';
            }
        };

        $msg = new SmsMessage('+8801712345678', 'Your code is: [REDACTED]', OtpPurpose::REGISTRATION_MOBILE, 'corr-1');
        $result = $provider->send($msg);

        $this->assertTrue($result->accepted);
        $this->assertSame('accepted', $result->status);
        $this->assertSame('msg-123', $result->providerMessageId);
    }

    public function test_fake_provider_can_simulate_temporary_failure(): void
    {
        $provider = new class implements SmsProviderInterface
        {
            public function send(SmsMessage $message): DeliveryResult
            {
                return DeliveryResult::temporaryFailure('fake', 'network_timeout');
            }

            public function providerName(): string
            {
                return 'fake';
            }
        };

        $msg = new SmsMessage('+8801712345678', 'code', OtpPurpose::LOGIN, 'corr-2');
        $result = $provider->send($msg);

        $this->assertFalse($result->accepted);
        $this->assertTrue($result->retryable);
        $this->assertSame('network_timeout', $result->errorCode);
    }

    public function test_fake_provider_can_simulate_permanent_failure(): void
    {
        $provider = new class implements SmsProviderInterface
        {
            public function send(SmsMessage $message): DeliveryResult
            {
                return DeliveryResult::permanentFailure('fake', 'invalid_number');
            }

            public function providerName(): string
            {
                return 'fake';
            }
        };

        $msg = new SmsMessage('+8801712345678', 'code', OtpPurpose::PASSWORD_RESET, 'corr-3');
        $result = $provider->send($msg);

        $this->assertFalse($result->accepted);
        $this->assertFalse($result->retryable);
        $this->assertSame('permanent_failure', $result->status);
    }

    public function test_provider_unavailable_is_retryable(): void
    {
        $result = DeliveryResult::providerUnavailable('mim');
        $this->assertFalse($result->accepted);
        $this->assertTrue($result->retryable);
        $this->assertSame('provider_unavailable', $result->status);
    }
}
