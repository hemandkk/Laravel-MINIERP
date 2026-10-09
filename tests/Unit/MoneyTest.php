<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_converts_between_decimal_strings_and_cents(): void
    {
        $this->assertSame(1050, Money::toCents('10.50'));
        $this->assertSame(1050, Money::toCents('10.5'));
        $this->assertSame(1000, Money::toCents('10'));
        $this->assertSame(1000, Money::toCents(10));
        $this->assertSame(5250, Money::toCents('52.5000'));
        $this->assertSame(-125, Money::toCents('-1.25'));

        $this->assertSame('52.50', Money::fromCents(5250));
        $this->assertSame('0.05', Money::fromCents(5));
        $this->assertSame('-1.25', Money::fromCents(-125));
    }

    public function test_it_multiplies_and_sums_without_floating_point_error(): void
    {
        // 0.1 + 0.2 != 0.3 in binary floating point; exact in cents.
        $this->assertSame('0.30', Money::sum(['0.10', '0.20']));
        $this->assertSame('52.50', Money::multiply('10.50', 5));
        $this->assertSame('0.00', Money::sum([]));
    }

    public function test_it_normalizes_null_aggregates_and_formats_for_display(): void
    {
        $this->assertSame('0.00', Money::normalize(null));
        $this->assertSame('1,234,567.80', Money::format('1234567.8'));
        $this->assertSame('999.00', Money::format('999'));
    }

    #[DataProvider('invalidAmounts')]
    public function test_it_rejects_invalid_amounts(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toCents($amount);
    }

    public static function invalidAmounts(): array
    {
        return [
            'text' => ['abc'],
            'three decimals' => ['1.234'],
            'scientific notation' => ['1e3'],
            'empty' => [''],
        ];
    }
}
