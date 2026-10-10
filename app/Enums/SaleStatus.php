<?php

declare(strict_types=1);

namespace App\Enums;

/** Lifecycle of a sale. */
enum SaleStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::CANCELLED, self::REFUNDED], true);
    }
}
