<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the authenticated user for non-HTTP callers (global scopes,
 * observers, queued jobs) without ever forcing the Passport guard to boot
 * when there is no authentication context.
 *
 * Why this exists: building the `api` guard instantiates Passport's
 * ResourceServer, which requires storage/oauth-private.key / oauth-public.key
 * to exist. Calling auth('api') directly from a global scope therefore made
 * `php artisan migrate --seed` explode with "Invalid key supplied" on a fresh
 * clone (before `passport:keys` has been run).
 */
final class TenantContext
{
    /** The current API user, or null when there is no HTTP auth context. */
    public static function user(): ?User
    {
        // Console commands (migrations, seeders, schedule) and queue workers
        // have no bearer token: tenant scoping is not applicable there and
        // services pass business ids explicitly instead.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        $user = Auth::guard('api')->user();

        return $user instanceof User ? $user : null;
    }

    /** Tenant id of the current user (null when there is no tenant context). */
    public static function businessId(): ?int
    {
        return self::user()?->active_business_id;
    }

    /** True when the acting user is a global administrator. */
    public static function isAdministration(): bool
    {
        $user = self::user();

        return $user !== null && $user->isAdmin();
    }
}
