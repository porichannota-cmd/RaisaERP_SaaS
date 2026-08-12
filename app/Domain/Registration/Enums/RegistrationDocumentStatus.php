<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

/**
 * Lifecycle status for staged identity documents.
 *
 * Documents exist in the registration_identity_documents staging table
 * until either claimed by a user account or expired/deleted.
 */
enum RegistrationDocumentStatus: string
{
    /** Upload accepted; file stored; awaiting validation. */
    case PENDING = 'pending';

    /** File successfully stored and passed initial format checks. */
    case UPLOADED = 'uploaded';

    /** File passed security/malware and format validation. */
    case VALIDATED = 'validated';

    /** Successfully claimed by a created user account. Terminal. */
    case CLAIMED = 'claimed';

    /** Rejected (security failure, invalid format, disallowed type). Terminal. */
    case REJECTED = 'rejected';

    /** Session TTL elapsed; document scheduled for purge. Terminal. */
    case EXPIRED = 'expired';

    /** Physical file purged from storage. Terminal. */
    case DELETED = 'deleted';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::CLAIMED,
            self::REJECTED,
            self::EXPIRED,
            self::DELETED,
        ]);
    }

    public function isClaimable(): bool
    {
        return in_array($this, [
            self::UPLOADED,
            self::VALIDATED,
        ]);
    }
}
