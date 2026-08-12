<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Registration\Contracts\SensitiveLookupHasherInterface;
use RuntimeException;

/**
 * HMAC-SHA256 keyed lookup hasher.
 *
 * Uses a dedicated server-held SENSITIVE_LOOKUP_SECRET (separate from APP_KEY)
 * to produce deterministic, collision-resistant lookup fingerprints for low-entropy
 * sensitive identifiers such as NID numbers and bank account numbers.
 *
 * Security rationale (PA-04):
 *   Raw SHA-256 of NID/account numbers is rejected because the finite input space
 *   (~170M valid BD NIDs) can be precomputed and compared offline if the DB is leaked.
 *   HMAC with a server-held key prevents this — even a full DB dump is useless without
 *   the key.
 *
 * Key governance:
 *   Key source: SENSITIVE_LOOKUP_SECRET environment variable
 *   Algorithm:  HMAC-SHA256
 *   Output:     64-character lowercase hex string
 *   Minimum key length: 32 bytes (256 bits) enforced on construction
 *
 * Key rotation:
 *   Rotating SENSITIVE_LOOKUP_SECRET requires re-computing all HMAC fields.
 *   A migration job must decrypt the original value and re-hash with the new key.
 *   KMS/Vault management is deferred — adapter can replace this class.
 */
final class HmacSensitiveLookupHasher implements SensitiveLookupHasherInterface
{
    private readonly string $key;

    /**
     * @param  string|null  $secret  Injected secret (null = read from env). Defaults to env.
     *
     * @throws RuntimeException If the secret is missing or too short.
     */
    public function __construct(?string $secret = null)
    {
        $resolved = $secret ?? (string) config('registration.sensitive_lookup_secret', '');

        if (strlen($resolved) < 32) {
            // In production, this MUST throw — no silent fallback to a weak key.
            // In testing, the test suite injects a safe deterministic test secret.
            throw new RuntimeException(
                'HmacSensitiveLookupHasher: SENSITIVE_LOOKUP_SECRET must be at least 32 characters. '.
                'Set this value in your environment configuration. '.
                'Do NOT use a weak or default literal in production.'
            );
        }

        $this->key = $resolved;
    }

    /**
     * Produce a keyed HMAC-SHA256 fingerprint of the normalised plaintext.
     *
     * Output is always a 64-character lowercase hex string.
     * Same plaintext + same key → same output (deterministic).
     * Different plaintext → different output (collision resistant).
     */
    public function hash(string $normalizedPlaintext): string
    {
        return hash_hmac('sha256', $normalizedPlaintext, $this->key);
    }

    /**
     * Constant-time comparison against a stored fingerprint.
     *
     * Uses hash_equals() to prevent timing-based enumeration attacks.
     */
    public function verify(string $normalizedPlaintext, string $storedFingerprint): bool
    {
        return hash_equals($storedFingerprint, $this->hash($normalizedPlaintext));
    }
}
