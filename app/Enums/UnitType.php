<?php

declare(strict_types=1);

namespace App\Enums;

/** Measurement family of a unit of measure. */
enum UnitType: string
{
    case UNIT = 'unit';
    case WEIGHT = 'weight';
    case VOLUME = 'volume';
    case LENGTH = 'length';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
