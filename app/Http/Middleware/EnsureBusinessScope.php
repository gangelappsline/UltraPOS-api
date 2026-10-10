<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Multi-tenant firewall.
 *
 * 1. Resolves the tenant for the request: an explicit {business} route
 *    parameter (model-bound or raw id), otherwise the authenticated user's
 *    active business.
 * 2. Verifies the authenticated user actually belongs to that tenant
 *    (admins are global and always pass).
 * 3. Binds the resolved Business into the container as "current_business"
 *    so services / global scopes can consume it without re-querying.
 */
final class EnsureBusinessScope
{
    public const string BINDING = 'current_business';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new ApiException('Unauthenticated', ErrorCode::UNAUTHENTICATED, null, 401);
        }

        $isAdmin = $user->hasRole('admin');

        // Explicit tenant in the route: {business} (bound model or raw id).
        $routeBusiness = $request->route('business');
        $requestedId = match (true) {
            $routeBusiness instanceof Business => $routeBusiness->getKey(),
            is_numeric($routeBusiness) => (int) $routeBusiness,
            default => null,
        };

        if ($requestedId !== null) {
            if (! $isAdmin && ! $user->belongsToBusiness($requestedId)) {
                throw new ApiException(
                    'You do not have access to this business',
                    ErrorCode::UNAUTHORIZED,
                    null,
                    403
                );
            }

            app()->instance(self::BINDING, Business::findOrFail($requestedId));

            return $next($request);
        }

        // No explicit tenant: fall back to the user's active business.
        $business = $user->activeBusiness();

        if ($business === null && ! $isAdmin) {
            throw new ApiException(
                'Your account is not associated with any business',
                ErrorCode::UNAUTHORIZED,
                null,
                403
            );
        }

        if ($business !== null) {
            app()->instance(self::BINDING, $business);
        }

        return $next($request);
    }
}
