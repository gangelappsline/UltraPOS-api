<?php

declare(strict_types=1);

namespace App\Enums;

/** System roles managed by Spatie Laravel Permission. */
enum UserRole: string
{
    case ADMIN = 'admin';
    case BUSINESS = 'business';
    case SELLER = 'seller';
    case CUSTOMER = 'customer';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::BUSINESS => 'Business Owner',
            self::SELLER => 'Seller / Cashier',
            self::CUSTOMER => 'Customer',
        };
    }

    /** Passport scopes are 1:1 with roles in UltraPOS. */
    public function scope(): string
    {
        return $this->value;
    }
}
