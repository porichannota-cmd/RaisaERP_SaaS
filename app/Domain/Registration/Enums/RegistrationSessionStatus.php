<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

/**
 * Registration session lifecycle states.
 *
 * A RegistrationSession progresses linearly from INITIATED to either
 * CONSUMED (success) or EXPIRED / CANCELLED / LOCKED (failure paths).
 */
enum RegistrationSessionStatus: string
{
    /** Session created; OTP send not yet triggered. */
    case INITIATED = 'initiated';

    /** OTP sent; waiting for client verification. */
    case OTP_PENDING = 'otp_pending';

    /** OTP successfully verified; session elevated trust. */
    case OTP_VERIFIED = 'otp_verified';

    /** Identity documents (NID/photo) upload in progress. */
    case IDENTITY_IN_PROGRESS = 'identity_in_progress';

    /** All Stage 1 prerequisites satisfied; ready for account creation. */
    case READY_FOR_ACCOUNT_CREATION = 'ready_for_account_creation';

    /** Session successfully consumed by account creation. Terminal. */
    case CONSUMED = 'consumed';

    /** Session TTL elapsed without completion. Terminal. */
    case EXPIRED = 'expired';

    /** Session explicitly cancelled (e.g. user started over). Terminal. */
    case CANCELLED = 'cancelled';

    /** Session locked due to repeated invalid token or security event. Terminal. */
    case LOCKED = 'locked';

    /**
     * Whether this session status is still actionable (not terminal).
     */
    public function isActionable(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Terminal states are final — the session cannot advance further.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::CONSUMED,
            self::EXPIRED,
            self::CANCELLED,
            self::LOCKED,
        ]);
    }

    /**
     * Whether identity document uploads are permitted in this state.
     * Requires OTP verification to have occurred first.
     */
    public function allowsDocumentUpload(): bool
    {
        return in_array($this, [
            self::OTP_VERIFIED,
            self::IDENTITY_IN_PROGRESS,
        ]);
    }
}
