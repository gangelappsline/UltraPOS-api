<?php

declare(strict_types=1);

namespace App\Enums;

/** Channel through which the sale was originated. */
enum SaleType: string
{
    case POS = 'pos';
    case ONLINE = 'online';
    case PHONE = 'phone';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
