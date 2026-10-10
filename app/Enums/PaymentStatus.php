<?php

declare(strict_types=1);

namespace App\Enums;

/** Payment state of a sale (derived from paid_amount vs total). */
enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
