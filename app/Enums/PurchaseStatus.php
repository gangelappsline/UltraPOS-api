<?php

declare(strict_types=1);

namespace App\Enums;

/** Lifecycle of a supplier purchase order. */
enum PurchaseStatus: string
{
    case DRAFT = 'draft';
    case ORDERED = 'ordered';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
