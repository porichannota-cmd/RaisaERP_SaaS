<?php

namespace App\Domain\Money\Exceptions;

use RuntimeException;

class InvalidAmountException extends RuntimeException
{
    public static function forFloat(): self
    {
        return new self("Native PHP floating-point arithmetic is forbidden for financial computation.");
    }
    
    public static function forNonInteger(string $value): self
    {
        return new self("Money amount must be an integer (minor units), got: $value");
    }
}
