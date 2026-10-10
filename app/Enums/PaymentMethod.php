<?php

declare(strict_types=1);

namespace App\Enums;

/** Tender types accepted at the POS. */
enum PaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case TRANSFER = 'transfer';
    case CREDIT = 'credit';
    case MIXED = 'mixed';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Cash-like methods must be reflected in the open cash register. */
    public function affectsCashRegister(): bool
    {
        return in_array($this, [self::CASH, self::MIXED], true);
    }
}
