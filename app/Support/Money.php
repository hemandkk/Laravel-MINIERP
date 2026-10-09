<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

/**
 * Exact two-decimal money arithmetic using integer cents.
 *
 * Amounts are exchanged as decimal strings (e.g. "52.50"), which is how
 * Eloquent's "decimal:2" cast and MySQL DECIMAL aggregates represent them,
 * so no value ever passes through a binary floating-point number.
 */
final class Money
{
    private const PATTERN = '/^(-)?(\d+)(?:\.(\d+))?$/';

    public static function toCents(string|int $amount): int
    {
        if (is_int($amount)) {
            return self::guard($amount * 100);
        }

        $amount = trim($amount);

        if (preg_match(self::PATTERN, $amount, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid monetary amount [{$amount}].");
        }

        $fraction = $matches[3] ?? '';

        // Database aggregates can return extra trailing zeros ("52.5000"); anything else is real precision loss.
        if (strlen($fraction) > 2 && rtrim(substr($fraction, 2), '0') !== '') {
            throw new InvalidArgumentException("Monetary amount [{$amount}] has more than two decimal places.");
        }

        $cents = self::guard((int) $matches[2] * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $matches[1] === '-' ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /**
     * Normalise any decimal representation (including NULL aggregates) to "0.00" form.
     */
    public static function normalize(string|int|null $amount): string
    {
        return self::fromCents(self::toCents($amount ?? 0));
    }

    public static function multiply(string|int $unitPrice, int $quantity): string
    {
        return self::fromCents(self::guard(self::toCents($unitPrice) * $quantity));
    }

    /**
     * @param  iterable<string|int>  $amounts
     */
    public static function sum(iterable $amounts): string
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total = self::guard($total + self::toCents($amount));
        }

        return self::fromCents($total);
    }

    /**
     * Human-readable representation with thousands separators, e.g. "1,234.50".
     */
    public static function format(string|int|null $amount): string
    {
        $normalized = self::normalize($amount);
        $negative = str_starts_with($normalized, '-');
        [$whole, $fraction] = explode('.', ltrim($normalized, '-'));

        $grouped = strrev(implode(',', str_split(strrev($whole), 3)));

        return ($negative ? '-' : '').$grouped.'.'.$fraction;
    }

    private static function guard(int|float $value): int
    {
        if (! is_int($value)) {
            throw new OverflowException('Monetary calculation overflowed the integer range.');
        }

        return $value;
    }
}
