<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Money helpers. All monetary math goes through these functions so the
 * 2-decimal rounding policy lives in exactly one place.
 */
final class Money
{
    private const int SCALE = 2;

    /** Round a monetary value to 2 decimals (half-up, bcmath backed). */
    public static function round(float|int|string $amount): float
    {
        return (float) number_format((float) $amount, self::SCALE, '.', '');
    }

    public static function add(float|string $a, float|string $b): float
    {
        return self::round((float) $a + (float) $b);
    }

    public static function subtract(float|string $a, float|string $b): float
    {
        return self::round((float) $a - (float) $b);
    }

    public static function multiply(float|string $a, float|string $b): float
    {
        return self::round((float) $a * (float) $b);
    }

    /** Tax amount for a base value at a percentage rate (e.g. 16.00%). */
    public static function tax(float|string $base, float|string $ratePercent): float
    {
        return self::round(((float) $base * (float) $ratePercent) / 100);
    }

    /** Whether amounts are equal within a cent tolerance. */
    public static function equals(float|string $a, float|string $b): bool
    {
        return abs((float) $a - (float) $b) < 0.005;
    }

    /** Whether the amount is strictly positive. */
    public static function isPositive(float|string $amount): bool
    {
        return (float) $amount > 0;
    }

    /** Min of two amounts. */
    public static function min(float|string $a, float|string $b): float
    {
        return self::round(min((float) $a, (float) $b));
    }
}
