<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Holds per-request metadata (request id) that must be embedded in every
 * API response envelope. Populated by the AssignRequestId middleware.
 */
final class RequestContext
{
    private static ?string $requestId = null;

    public static function setRequestId(string $requestId): void
    {
        self::$requestId = $requestId;
    }

    public static function requestId(): string
    {
        return self::$requestId ??= (string) Str::uuid();
    }

    /** Reset state (used between tests / octane requests). */
    public static function flush(): void
    {
        self::$requestId = null;
    }
}
