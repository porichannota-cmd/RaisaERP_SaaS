<?php

namespace App\Domain\Communication\Enums;

enum OtpStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case VERIFIED = 'verified';
    case CONSUMED = 'consumed';
    case EXPIRED = 'expired';
    case LOCKED = 'locked';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::SENT]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::VERIFIED, self::CONSUMED, self::EXPIRED, self::LOCKED, self::FAILED, self::CANCELLED]);
    }
}
