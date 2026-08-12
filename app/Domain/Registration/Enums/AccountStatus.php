<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

/**
 * Canonical account lifecycle states.
 *
 * PA-05: Authorization uses these explicit states — never a percentage.
 * Percentage-based completion exists for UX reporting only.
 */
enum AccountStatus: string
{
    /** Mobile submitted; OTP not yet verified. */
    case PENDING_MOBILE_VERIFICATION = 'pending_mobile_verification';

    /** OTP verified; minimal platform user account created; limited access permitted. */
    case MOBILE_VERIFIED = 'mobile_verified';

    /** Stage 1 complete; Stage 2 profile not yet at required threshold. */
    case PROFILE_INCOMPLETE = 'profile_incomplete';

    /** Profile prerequisites satisfied; awaiting human or system approval. */
    case PENDING_APPROVAL = 'pending_approval';

    /** Approval granted; role/grant activated; full IAM-controlled access. */
    case ACTIVE = 'active';

    /** Registration rejected; reason stored. Terminal — cannot auto-recover. */
    case REJECTED = 'rejected';

    /** Temporary administrative hold; authentication denied. */
    case SUSPENDED = 'suspended';

    /** Hard block (fraud / security violation); authentication denied. */
    case BLOCKED = 'blocked';

    /**
     * Whether the account is in a state that permits authentication at all.
     */
    public function mayAuthenticate(): bool
    {
        return in_array($this, [
            self::MOBILE_VERIFIED,
            self::PROFILE_INCOMPLETE,
            self::PENDING_APPROVAL,
            self::ACTIVE,
        ]);
    }

    /**
     * Whether the account is in a fully activated state with normal operations.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Whether the account is hard-blocked from any access.
     */
    public function isHardBlocked(): bool
    {
        return in_array($this, [
            self::REJECTED,
            self::SUSPENDED,
            self::BLOCKED,
        ]);
    }
}
