<?php

declare(strict_types=1);

namespace App\Domain\Registration\Contracts;

/**
 * Abstraction boundary for deterministic keyed fingerprints of sensitive lookup values.
 *
 * PA-04 (Critical): Raw SHA-256 is REJECTED for low-entropy sensitive identifiers
 * (NID numbers, bank account numbers) because the finite NID/account space can be
 * enumerated offline if the DB is compromised.
 *
 * This interface produces HMAC-SHA256 output with a server-held secret key,
 * preventing offline dictionary/precomputation attacks even if the DB is leaked.
 *
 * Usage model:
 *   - hash()  → produces the lookup fingerprint stored in the DB (e.g. nid_number_hmac)
 *   - verify() → constant-time comparison of a candidate plaintext against stored fingerprint
 *
 * Security invariants:
 *  - The key (SENSITIVE_LOOKUP_SECRET) MUST NOT be stored in the database.
 *  - The fingerprint is deterministic: same plaintext + same key → same output.
 *  - The fingerprint is NOT reversible — it is a one-way keyed hash.
 *  - Fingerprint output MUST NOT be displayed to users.
 *  - Missing or weak key MUST fail safely (reject, not fall back to insecure default).
 */
interface SensitiveLookupHasherInterface
{
    /**
     * Produce a keyed HMAC fingerprint of the normalised plaintext.
     *
     * @param  string  $normalizedPlaintext  Pre-normalised input (e.g. canonical NID).
     * @return string 64-character hex HMAC-SHA256 output.
     *
     * @throws \RuntimeException If the key is missing or below minimum strength.
     */
    public function hash(string $normalizedPlaintext): string;

    /**
     * Constant-time comparison of a candidate plaintext against a stored fingerprint.
     *
     * @param  string  $normalizedPlaintext  Candidate plaintext.
     * @param  string  $storedFingerprint  Fingerprint from DB.
     * @return bool True if the candidate matches.
     */
    public function verify(string $normalizedPlaintext, string $storedFingerprint): bool;
}
