<?php

namespace App\Domain\Money\Exceptions;

use RuntimeException;

class MoneyMismatchException extends RuntimeException
{
    public static function forCurrencies(string $c1, string $c2): self
    {
        return new self("Cannot perform arithmetic on mismatched currencies: [$c1] and [$c2]");
    }
}
