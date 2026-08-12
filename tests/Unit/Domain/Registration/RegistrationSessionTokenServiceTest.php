<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\Services\RegistrationSessionTokenService;
use RuntimeException;
use Tests\TestCase;

class RegistrationSessionTokenServiceTest extends TestCase
{
    public function test_generates_token_and_hash(): void
    {
        $service = new RegistrationSessionTokenService('12345678901234567890123456789012');

        $result = $service->generate();

        $this->assertArrayHasKey('rawToken', $result);
        $this->assertArrayHasKey('storedHash', $result);

        $this->assertNotEquals($result['rawToken'], $result['storedHash']);

        // Output should be base64url and hex respectively
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $result['rawToken']);
        $this->assertEquals(64, strlen($result['storedHash']));
    }

    public function test_verification_succeeds_with_correct_token(): void
    {
        $service = new RegistrationSessionTokenService('12345678901234567890123456789012');

        $result = $service->generate();

        $this->assertTrue($service->verify($result['rawToken'], $result['storedHash']));
    }

    public function test_verification_fails_with_wrong_token(): void
    {
        $service = new RegistrationSessionTokenService('12345678901234567890123456789012');

        $result = $service->generate();

        $this->assertFalse($service->verify('wrong_token', $result['storedHash']));
    }

    public function test_verification_fails_cross_session(): void
    {
        $service = new RegistrationSessionTokenService('12345678901234567890123456789012');

        $session1 = $service->generate();
        $session2 = $service->generate();

        $this->assertFalse($service->verify($session1['rawToken'], $session2['storedHash']));
    }

    public function test_requires_minimum_key_length(): void
    {
        $this->expectException(RuntimeException::class);
        new RegistrationSessionTokenService('short');
    }
}
