<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingCycle: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function months(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::YEARLY => 12,
        };
    }
}
