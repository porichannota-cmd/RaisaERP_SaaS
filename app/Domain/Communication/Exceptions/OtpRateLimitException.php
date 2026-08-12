<?php

namespace App\Domain\Communication\Exceptions;

class OtpRateLimitException extends \RuntimeException
{
    public function __construct(
        string $message = 'OTP rate limit exceeded.',
        public readonly int $retryAfter = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 429, $previous);
    }
}
