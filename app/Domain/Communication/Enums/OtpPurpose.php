<?php

namespace App\Domain\Communication\Enums;

enum OtpPurpose: string
{
    case REGISTRATION_MOBILE = 'registration.mobile';
    case REGISTRATION_EMAIL = 'registration.email';
    case LOGIN = 'login';
    case PASSWORD_RESET = 'password.reset';
    case TWO_FACTOR = 'two_factor';
    case DEVICE_VERIFY = 'device.verify';
    case WITHDRAWAL_VERIFY = 'withdrawal.verify';
    case EMAIL_VERIFY = 'email.verify';
    case MOBILE_CHANGE = 'mobile.change';
    case HIGH_RISK_ACTION = 'high_risk_action';

    /**
     * Whether this purpose allows unauthenticated (public) OTP flows.
     */
    public function isPublic(): bool
    {
        return in_array($this, [
            self::REGISTRATION_MOBILE,
            self::REGISTRATION_EMAIL,
            self::PASSWORD_RESET,
        ]);
    }
}
