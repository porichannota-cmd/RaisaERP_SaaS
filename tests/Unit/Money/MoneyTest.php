<?php

namespace Tests\Unit\Money;

use App\Domain\Money\Currency;
use App\Domain\Money\Exceptions\InvalidAmountException;
use App\Domain\Money\Exceptions\MoneyMismatchException;
use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_creates_money_from_integer_minor_units()
    {
        $money = Money::of('123450', 'BDT');
        $this->assertEquals('123450', $money->amountMinor());
        $this->assertEquals('BDT', $money->currency()->getCode());
        $this->assertEquals('৳1,234.50', $money->format());
    }

    public function test_it_prevents_float_initialization()
    {
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('forbidden for financial computation');
        Money::of(1234.50, 'BDT');
    }

    public function test_it_prevents_string_float_initialization()
    {
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('must be an integer');
        Money::of('1234.50', 'BDT');
    }

    public function test_addition()
    {
        $m1 = Money::of('10000', 'BDT'); // 100.00
        $m2 = Money::of('5050', 'BDT');  // 50.50
        $result = $m1->add($m2);
        
        $this->assertEquals('15050', $result->amountMinor());
    }

    public function test_subtraction_and_negatives()
    {
        $m1 = Money::of('5000', 'BDT');
        $m2 = Money::of('8000', 'BDT');
        $result = $m1->subtract($m2);
        
        $this->assertEquals('-3000', $result->amountMinor());
        $this->assertTrue($result->isNegative());
        $this->assertEquals('৳-30.00', $result->format());
    }

    public function test_mismatched_currency_throws()
    {
        $m1 = Money::of('100', 'BDT');
        $m2 = Money::of('100', 'USD');
        
        $this->expectException(MoneyMismatchException::class);
        $m1->add($m2);
    }

    public function test_multiply_by_rate_and_half_up_rounding()
    {
        // 10.55 BDT * 0.15 tax = 1.5825 BDT = 158.25 paisa -> rounds to 158 paisa
        $money = Money::of('1055', 'BDT');
        $tax = $money->multiplyByRate('0.15');
        
        $this->assertEquals('158', $tax->amountMinor());
        
        // 10.57 BDT * 0.15 = 1.5855 BDT = 158.55 paisa -> rounds up to 159 paisa
        $money2 = Money::of('1057', 'BDT');
        $tax2 = $money2->multiplyByRate('0.15');
        
        $this->assertEquals('159', $tax2->amountMinor());
    }

    public function test_allocation()
    {
        // 100 paisa divided by 3 -> 34, 33, 33
        $money = Money::of('100', 'BDT');
        $portions = $money->allocate(3);
        
        $this->assertCount(3, $portions);
        $this->assertEquals('34', $portions[0]->amountMinor());
        $this->assertEquals('33', $portions[1]->amountMinor());
        $this->assertEquals('33', $portions[2]->amountMinor());
    }

    public function test_json_serialization()
    {
        $money = Money::of('123450', 'BDT');
        $json = json_encode($money);
        $this->assertEquals('{"amount_minor":"123450","currency":"BDT","formatted":"\u09f31,234.50"}', $json);
    }
}
