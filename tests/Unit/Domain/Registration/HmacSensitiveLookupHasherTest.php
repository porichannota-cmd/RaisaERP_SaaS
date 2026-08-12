<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\Services\HmacSensitiveLookupHasher;
use RuntimeException;
use Tests\TestCase;

class HmacSensitiveLookupHasherTest extends TestCase
{
    private const TEST_SECRET = '12345678901234567890123456789012';

    public function test_produces_deterministic_fingerprint(): void
    {
        $hasher = new HmacSensitiveLookupHasher(self::TEST_SECRET);

        $plaintext = '8899112233';

        $hash1 = $hasher->hash($plaintext);
        $hash2 = $hasher->hash($plaintext);

        $this->assertEquals($hash1, $hash2);

        // Output should be hex and 64 chars
        $this->assertEquals(64, strlen($hash1));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $hash1);
    }

    public function test_different_inputs_produce_different_fingerprints(): void
    {
        $hasher = new HmacSensitiveLookupHasher(self::TEST_SECRET);

        $hash1 = $hasher->hash('input1');
        $hash2 = $hasher->hash('input2');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_different_secrets_produce_different_fingerprints(): void
    {
        $hasher1 = new HmacSensitiveLookupHasher('12345678901234567890123456789012');
        $hasher2 = new HmacSensitiveLookupHasher('23456789012345678901234567890123');

        $hash1 = $hasher1->hash('input');
        $hash2 = $hasher2->hash('input');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_verification_succeeds(): void
    {
        $hasher = new HmacSensitiveLookupHasher(self::TEST_SECRET);

        $hash = $hasher->hash('input');

        $this->assertTrue($hasher->verify('input', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    public function test_throws_if_secret_is_too_short(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('at least 32 characters');

        new HmacSensitiveLookupHasher('short');
    }
}
