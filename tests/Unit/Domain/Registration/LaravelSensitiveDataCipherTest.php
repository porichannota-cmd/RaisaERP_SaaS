<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\Services\LaravelSensitiveDataCipher;
use RuntimeException;
use Tests\TestCase;

class LaravelSensitiveDataCipherTest extends TestCase
{
    public function test_encrypts_and_decrypts_data(): void
    {
        $cipher = new LaravelSensitiveDataCipher;
        $plaintext = 'sensitive-data';

        $ciphertext = $cipher->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $ciphertext);
        $this->assertStringNotContainsString($plaintext, $ciphertext);

        $decrypted = $cipher->decrypt($ciphertext);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_throws_exception_on_tampered_ciphertext(): void
    {
        $cipher = new LaravelSensitiveDataCipher;
        $ciphertext = $cipher->encrypt('sensitive-data');

        $tampered = 'invalid-payload-not-base64-or-json';

        $this->expectException(RuntimeException::class);
        $cipher->decrypt($tampered);
    }
}
