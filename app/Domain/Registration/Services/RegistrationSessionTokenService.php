<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

/**
 * Secure opaque registration session token service.
 *
 * PA-07: Registration sessions act as the pre-user trust boundary.
 *        The session token is the single credential that authenticates
 *        subsequent Stage 1 operations (OTP verify, document upload, account creation).
 *
 * Security invariants:
 *  1. Raw token is generated using cryptographically secure random bytes.
 *  2. Raw token is returned to the caller ONCE at session creation.
 *  3. Raw token is NEVER stored — only the HMAC fingerprint is persisted.
 *  4. Raw token is NEVER logged anywhere in the application.
 *  5. Verification uses constant-time comparison to prevent timing attacks.
 *  6. Token entropy: TOKEN_BYTES = 32 bytes = 256 bits.
 *
 * Token format:
 *  - Encoded as base64url (RFC 4648 §5) for URL-safe transport.
 *  - Stored fingerprint: hash_hmac('sha256', rawToken, SESSION_HMAC_SECRET)
 *    → 64-character lowercase hex.
 */
final class RegistrationSessionTokenService
{
    /** Entropy: 32 bytes = 256 bits. */
    private const TOKEN_BYTES = 32;

    private readonly string $hmacKey;

    /**
     * @param  string|null  $hmacKey  Injected key (null = read from env). Defaults to env.
     *
     * @throws \RuntimeException If the HMAC key is missing or too short.
     */
    public function __construct(?string $hmacKey = null)
    {
        $resolved = $hmacKey ?? (string) config('registration.session_hmac_secret', '');

        if (strlen($resolved) < 32) {
            throw new \RuntimeException(
                'RegistrationSessionTokenService: SESSION_HMAC_SECRET must be at least 32 characters. '.
                'Configure this in your environment. Do NOT use a weak default in production.'
            );
        }

        $this->hmacKey = $resolved;
    }

    /**
     * Generate a new secure session token pair.
     *
     * Returns:
     *   rawToken      — opaque base64url string to return to the caller ONCE.
     *   storedHash    — HMAC-SHA256 fingerprint to persist in the DB.
     *
     * @return array{rawToken: string, storedHash: string}
     */
    public function generate(): array
    {
        $rawBytes = random_bytes(self::TOKEN_BYTES);
        $rawToken = rtrim(strtr(base64_encode($rawBytes), '+/', '-_'), '=');
        $stored = $this->computeFingerprint($rawToken);

        // Explicitly zero the intermediate bytes reference where possible.
        // PHP does not guarantee secure memory zeroing, but we do not hold
        // the raw token in any persistent variable beyond this return.
        unset($rawBytes);

        return [
            'rawToken' => $rawToken,
            'storedHash' => $stored,
        ];
    }

    /**
     * Verify a raw token candidate against a stored fingerprint.
     *
     * Uses hash_equals() for constant-time comparison.
     *
     * @param  string  $rawToken  Candidate raw token from request.
     * @param  string  $storedFingerprint  Fingerprint from DB.
     */
    public function verify(string $rawToken, string $storedFingerprint): bool
    {
        return hash_equals($storedFingerprint, $this->computeFingerprint($rawToken));
    }

    /**
     * Compute the HMAC-SHA256 fingerprint of a raw token.
     */
    private function computeFingerprint(string $rawToken): string
    {
        return hash_hmac('sha256', $rawToken, $this->hmacKey);
    }
}
