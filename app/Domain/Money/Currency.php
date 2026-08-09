<?php

namespace App\Domain\Money;

use InvalidArgumentException;
use RuntimeException;

class Currency
{
    /**
     * Map of supported currencies and their scale (minor unit exponent).
     * BDT (Paisa) = 2, USD (Cents) = 2, JPY (Yen) = 0.
     */
    private static array $supported = [
        'BDT' => ['scale' => 2, 'numeric_code' => '050', 'symbol' => '৳'],
        'USD' => ['scale' => 2, 'numeric_code' => '840', 'symbol' => '$'],
        'JPY' => ['scale' => 0, 'numeric_code' => '392', 'symbol' => '¥'],
    ];

    private string $code;
    private int $scale;
    private string $symbol;

    public function __construct(string $code)
    {
        $code = strtoupper($code);
        if (!isset(self::$supported[$code])) {
            throw new InvalidArgumentException("Currency [$code] is not supported.");
        }

        $this->code = $code;
        $this->scale = self::$supported[$code]['scale'];
        $this->symbol = self::$supported[$code]['symbol'];
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->getCode();
    }
}
