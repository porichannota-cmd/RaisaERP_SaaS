<?php

declare(strict_types=1);

namespace App\Domain\Registration\Contracts;

/**
 * Abstraction boundary for symmetric encryption of sensitive data at rest.
 *
 * PA-04: Business/domain services must not scatter direct Crypt calls.
 * This interface allows future replacement with AWS KMS, HashiCorp Vault,
 * or HSM adapters without changing domain code.
 *
 * Security invariants:
 *  - Plaintext MUST NEVER appear in logs.
 *  - Plaintext MUST NEVER appear in audit payloads.
 *  - Plaintext MUST NEVER be used as a public identifier.
 *  - Ciphertext is non-deterministic (AES-CBC produces different output each call).
 *  - Ciphertext MUST NOT be used as a lookup key.
 */
interface SensitiveDataCipherInterface
{
    /**
     * Encrypt a plaintext string and return the ciphertext.
     *
     * @return string Opaque ciphertext for storage
     *
     * @throws \RuntimeException If encryption fails.
     */
    public function encrypt(string $plaintext): string;

    /**
     * Decrypt a previously encrypted ciphertext.
     *
     * @return string Recovered plaintext
     *
     * @throws \RuntimeException If decryption fails (tampered or wrong key).
     */
    public function decrypt(string $ciphertext): string;
}
