<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

/**
 * Registration source — resolved server-side only; never client-supplied.
 *
 * Security invariant: the source value does not grant authorization.
 * Authorization uses Wave 1A IAM exclusively.
 */
enum RegistrationSource: string
{
    /** Self-service public web registration. */
    case PUBLIC = 'public';

    /** Invited by an authorized tenant actor with a bound invitation token. */
    case INVITATION = 'invitation';

    /** Account created by an internal HR administrator (staff, employee). */
    case INTERNAL_HR = 'internal_hr';

    /** Dealer-specific onboarding channel. */
    case DEALER = 'dealer';

    /** Entrepreneur-specific channel. */
    case ENTREPRENEUR = 'entrepreneur';

    /** Customer self-registration. */
    case CUSTOMER_SELF = 'customer_self';

    /** CRM lead converted to platform account. */
    case CRM_CONVERSION = 'crm_conversion';

    /** Machine / service / API registration. */
    case API = 'api';

    /** Android/iOS dedicated mobile application. */
    case MOBILE_APP = 'mobile_app';

    /** QR-code triggered registration. */
    case QR = 'qr';

    /** Direct account creation by a Super Admin or Tenant Admin. */
    case ADMIN_CREATED = 'admin_created';

    /**
     * Whether the source is a self-service (unauthenticated) flow.
     * Used to determine validation rules and invitation requirements.
     */
    public function isSelfService(): bool
    {
        return in_array($this, [
            self::PUBLIC,
            self::CUSTOMER_SELF,
            self::MOBILE_APP,
            self::QR,
        ]);
    }

    /**
     * Whether the source requires a pre-authorized invitation token.
     */
    public function requiresInvitationToken(): bool
    {
        return $this === self::INVITATION;
    }
}
