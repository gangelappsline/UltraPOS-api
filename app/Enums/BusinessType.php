<?php

declare(strict_types=1);

namespace App\Enums;

/** Vertical supported by the POS (drives feature flags via product metadata). */
enum BusinessType: string
{
    case STORE = 'store';
    case GROCERY = 'grocery';
    case PHARMACY = 'pharmacy';
    case RESTAURANT = 'restaurant';
    case HARDWARE = 'hardware';
    case OTHER = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::STORE => 'Retail store',
            self::GROCERY => 'Grocery / greengrocer',
            self::PHARMACY => 'Pharmacy',
            self::RESTAURANT => 'Restaurant',
            self::HARDWARE => 'Hardware store',
            self::OTHER => 'Other',
        };
    }

    /** Whether this vertical typically tracks product expiration dates. */
    public function tracksExpirationDates(): bool
    {
        return in_array($this, [self::PHARMACY, self::GROCERY, self::RESTAURANT], true);
    }

    /** Whether this vertical typically sells by weight/volume units. */
    public function sellsByMeasure(): bool
    {
        return in_array($this, [self::GROCERY, self::HARDWARE, self::RESTAURANT], true);
    }
}
