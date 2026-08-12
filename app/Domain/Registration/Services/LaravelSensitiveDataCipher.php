<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Laravel application-encryption backed cipher.
 *
 * Initial implementation uses Laravel's AES-256-CBC via APP_KEY.
 * This class is the ONLY place in the codebase that calls Crypt directly
 * for sensitive registration data — all domain services depend on the interface.
 *
 * Key governance:
 *   - Key: APP_KEY (managed by Laravel key:generate)
 *   - Algorithm: AES-256-CBC
 *   - Ciphertext is non-deterministic (safe for storage; not suitable as lookup key)
 *
 * Future replaceability:
 *   - Swap this class for an AwsKmsCipher or VaultCipher adapter without
 *     changing any domain code. The interface is the only dependency.
 */
final class LaravelSensitiveDataCipher implements SensitiveDataCipherInterface
{
    /**
     * Encrypt a plaintext string using the application encryption key.
     *
     * @throws RuntimeException If encryption fails.
     */
    public function encrypt(string $plaintext): string
    {
        try {
            return Crypt::encryptString($plaintext);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'SensitiveDataCipher: encryption failed.',
                previous: $e
            );
        }
    }

    /**
     * Decrypt a previously encrypted ciphertext.
     *
     * @throws RuntimeException If decryption fails (tampered ciphertext or wrong key).
     */
    public function decrypt(string $ciphertext): string
    {
        try {
            return Crypt::decryptString($ciphertext);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'SensitiveDataCipher: decryption failed — ciphertext may be tampered or encrypted with a different key.',
                previous: $e
            );
        }
    }
}
