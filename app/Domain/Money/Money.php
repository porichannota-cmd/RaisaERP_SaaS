<?php

namespace App\Domain\Money;

use App\Domain\Money\Exceptions\InvalidAmountException;
use App\Domain\Money\Exceptions\MoneyMismatchException;
use JsonSerializable;
use Stringable;

class Money implements JsonSerializable, Stringable
{
    private string $amountMinor;
    private Currency $currency;

    /**
     * @param string|int|float $amountMinor Amount in minor units (integer strings or ints). No floats.
     * @param Currency $currency
     */
    public function __construct(string|int|float $amountMinor, Currency $currency)
    {
        if (is_float($amountMinor)) {
            throw InvalidAmountException::forFloat();
        }

        $amountStr = (string)$amountMinor;
        
        // Basic check: must be digits, optionally prefixed with minus. No decimals.
        if (!preg_match('/^-?\d+$/', $amountStr)) {
            throw InvalidAmountException::forNonInteger($amountStr);
        }

        $this->amountMinor = $amountStr;
        $this->currency = $currency;
    }

    public static function of(string|int|float $amountMinor, string $currencyCode): self
    {
        return new self($amountMinor, new Currency($currencyCode));
    }

    public static function zero(string $currencyCode): self
    {
        return self::of('0', $currencyCode);
    }

    public function amountMinor(): string
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function isZero(): bool
    {
        return bccomp($this->amountMinor, '0', 0) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amountMinor, '0', 0) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amountMinor, '0', 0) > 0;
    }

    public function compare(Money $other): int
    {
        $this->assertSameCurrency($other);
        return bccomp($this->amountMinor, $other->amountMinor(), 0);
    }

    public function equals(Money $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        $newAmount = bcadd($this->amountMinor, $other->amountMinor(), 0);
        return new self($newAmount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        $newAmount = bcsub($this->amountMinor, $other->amountMinor(), 0);
        return new self($newAmount, $this->currency);
    }

    /**
     * Multiply by a rate (which could be a decimal like '0.15' or '110.25')
     * and round HALF_UP to the nearest minor unit.
     */
    public function multiplyByRate(string|int $rate): self
    {
        if (is_float($rate)) {
            throw InvalidAmountException::forFloat();
        }
        
        // Multiply using bcmath with higher precision to do rounding later
        $precision = 10; 
        $product = bcmul($this->amountMinor, (string)$rate, $precision);
        
        // Round HALF_UP manually
        $rounded = $this->bcRoundHalfUp($product);
        
        return new self($rounded, $this->currency);
    }

    /**
     * Allocate the money into N portions (e.g. splitting a bill).
     * Distributes remainders to the first elements.
     * @return Money[]
     */
    public function allocate(int $n): array
    {
        if ($n < 1) {
            throw new \InvalidArgumentException('Cannot allocate to less than 1 target.');
        }

        $baseAmount = bcdiv($this->amountMinor, (string)$n, 0);
        $remainder = bcmod($this->amountMinor, (string)$n);

        $results = [];
        $isNegative = $this->isNegative();
        $absRemainder = abs((int)$remainder);

        for ($i = 0; $i < $n; $i++) {
            $amount = $baseAmount;
            if ($i < $absRemainder) {
                $amount = bcadd($amount, $isNegative ? '-1' : '1', 0);
            }
            $results[] = new self($amount, $this->currency);
        }

        return $results;
    }

    public function format(): string
    {
        $scale = $this->currency->getScale();
        
        if ($scale > 0) {
            $divisor = bcpow('10', (string)$scale, 0);
            $major = bcdiv($this->amountMinor, $divisor, 0);
            
            // To pad minor correctly with leading zeros
            $isNegative = $this->isNegative();
            $absMinorStr = ltrim($this->amountMinor, '-');
            $minorStr = str_pad($absMinorStr, $scale, '0', STR_PAD_LEFT);
            $minor = substr($minorStr, -$scale);
            
            // Re-apply negative sign if needed (but handled by major string except if major is 0 and minor is not)
            if ($isNegative && $major === '0') {
                $major = '-0';
            }
            
            $formattedNumber = number_format((float)($major . '.' . $minor), $scale);
        } else {
            $formattedNumber = number_format((float)$this->amountMinor, 0);
        }

        return $this->currency->getSymbol() . $formattedNumber;
    }

    public function jsonSerialize(): array
    {
        return [
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency->getCode(),
            'formatted' => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->amountMinor;
    }

    private function assertSameCurrency(Money $other): void
    {
        if (!$this->currency->equals($other->currency())) {
            throw MoneyMismatchException::forCurrencies($this->currency->getCode(), $other->currency()->getCode());
        }
    }

    /**
     * Helper to round HALF_UP using BC math string logic.
     */
    private function bcRoundHalfUp(string $number): string
    {
        if (!str_contains($number, '.')) {
            return $number;
        }

        [$intPart, $fracPart] = explode('.', $number);
        
        if ($fracPart === '') {
            return $intPart;
        }

        $firstDecimal = (int) substr($fracPart, 0, 1);
        $isNegative = str_starts_with($intPart, '-');
        
        if ($firstDecimal >= 5) {
            if ($isNegative) {
                return bcsub($intPart, '1', 0);
            }
            return bcadd($intPart, '1', 0);
        }

        return $intPart;
    }
}
