<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Models\User;
use RuntimeException;

/**
 * Enterprise User ID Generator.
 *
 * PA-02: Format is USR-{YEAR}-{8_CHAR_CRYPTOGRAPHIC_ENTROPY}
 *
 * Example: USR-2026-A7K9M2QX
 *
 * Security invariants:
 *  - Entropy uses cryptographically secure random bytes via random_bytes().
 *  - No role, position, tenant, NID, mobile, or email information embedded.
 *  - No sequential counter (non-enumerable).
 *  - DB UNIQUE constraint is the authoritative collision guard.
 *  - Application-level retry loop handles the rare collision case.
 *  - The ID is immutable once assigned — domain layer must not mutate it.
 *  - The ID must never be client-supplied or accepted from external input.
 *
 *  The enterprise_user_id identifies the platform person only.
 */
class EnterpriseUserIdGenerator
{
    /** Maximum collision-retry attempts before giving up. */
    private const MAX_RETRIES = 5;

    /** Year segment length. */
    private const YEAR_FORMAT = 'Y';

    /** Entropy: 4 random bytes → 8 uppercase hex characters. */
    private const ENTROPY_BYTES = 4;

    /**
     * Generate a unique enterprise user ID and persist it to a User record.
     *
     * This method is called within the account creation transaction.
     * It writes to the User model; callers must handle the DB transaction.
     *
     * @throws RuntimeException If a unique ID cannot be generated after MAX_RETRIES.
     */
    public function generate(): string
    {
        $attempts = 0;

        while ($attempts < self::MAX_RETRIES) {
            $candidate = $this->buildCandidate();

            if (! User::where('enterprise_user_id', $candidate)->exists()) {
                return $candidate;
            }

            $attempts++;
        }

        throw new RuntimeException(
            'EnterpriseUserIdGenerator: failed to generate a unique enterprise_user_id after '.
            self::MAX_RETRIES.' attempts. This is an extremely unlikely event; verify DB connectivity.'
        );
    }

    /**
     * Build a single candidate ID.
     *
     * Format: USR-{YYYY}-{8_UPPER_HEX}
     * The entropy segment uses 4 cryptographically random bytes, encoded as
     * uppercase hexadecimal (4 bytes = 8 hex characters = 2^32 = ~4.3B possibilities).
     */
    protected function buildCandidate(): string
    {
        $year = date(self::YEAR_FORMAT);
        $entropy = strtoupper(bin2hex(random_bytes(self::ENTROPY_BYTES)));

        return sprintf('USR-%s-%s', $year, $entropy);
    }
}
