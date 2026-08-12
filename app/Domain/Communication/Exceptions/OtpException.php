<?php

namespace App\Domain\Communication\Exceptions;

class OtpException extends \RuntimeException
{
    public static function expired(): self
    {
        return new self('OTP has expired.', 422);
    }

    public static function locked(): self
    {
        return new self('OTP is locked due to too many failed attempts.', 422);
    }

    public static function alreadyUsed(): self
    {
        return new self('OTP has already been used.', 422);
    }

    public static function invalid(): self
    {
        return new self('OTP is invalid.', 422);
    }

    public static function purposeMismatch(): self
    {
        return new self('OTP purpose does not match.', 422);
    }

    public static function notFound(): self
    {
        return new self('OTP record not found.', 404);
    }

    public static function resendTooSoon(int $retryAfter): self
    {
        $e = new self("Please wait {$retryAfter} seconds before requesting a new OTP.", 429);
        $e->retryAfter = $retryAfter;

        return $e;
    }

    public static function deliveryFailed(string $reason = ''): self
    {
        return new self('OTP delivery failed.'.($reason ? " {$reason}" : ''), 502);
    }

    public int $retryAfter = 0;
}
