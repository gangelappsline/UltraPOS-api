<?php

declare(strict_types=1);

namespace App\Enums;

/** Money movements inside a cash register session. */
enum CashMovementType: string
{
    case OPENING = 'opening';
    case SALE = 'sale';
    case REFUND = 'refund';
    case WITHDRAWAL = 'withdrawal';
    case DEPOSIT = 'deposit';
    case CLOSING = 'closing';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Signed effect on the drawer balance. */
    public function sign(): int
    {
        return match ($this) {
            self::OPENING, self::SALE, self::DEPOSIT => 1,
            self::REFUND, self::WITHDRAWAL, self::CLOSING => -1,
        };
    }
}
