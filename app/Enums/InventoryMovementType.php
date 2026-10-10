<?php

declare(strict_types=1);

namespace App\Enums;

/** Kinds of stock movements tracked in inventory_movements. */
enum InventoryMovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
    case TRANSFER = 'transfer';
    case LOSS = 'loss';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Signed multiplier applied to the product stock. */
    public function stockSign(): int
    {
        return match ($this) {
            self::PURCHASE, self::RETURN => 1,
            self::SALE, self::LOSS => -1,
            self::ADJUSTMENT, self::TRANSFER => 1, // quantity is signed by caller
        };
    }
}
