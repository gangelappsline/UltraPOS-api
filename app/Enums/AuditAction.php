<?php

declare(strict_types=1);

namespace App\Enums;

/** Actions recorded by the AuditObserver in audit_logs. */
enum AuditAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case RESTORED = 'restored';
    case FORCE_DELETED = 'force_deleted';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
