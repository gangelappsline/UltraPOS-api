<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the authenticated user's business context at runtime:
 * timezone, locale and currency are per-tenant settings.
 */
final class SetBusinessLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->user()?->activeBusiness();

        if ($business !== null) {
            config(['app.timezone' => $business->timezone ?: config('app.timezone')]);
            date_default_timezone_set((string) config('app.timezone'));

            $locale = $business->settings['locale'] ?? null;
            if (is_string($locale) && $locale !== '') {
                app()->setLocale($locale);
            }

            config(['ultrapos.currency' => $business->currency]);
        }

        return $next($request);
    }
}
