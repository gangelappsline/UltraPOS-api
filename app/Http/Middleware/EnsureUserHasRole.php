<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: role:business,seller
 *
 * Guarantees the authenticated user holds at least one of the given Spatie
 * roles. The global "admin" role implicitly passes every check (super admin).
 */
final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new ApiException('Unauthenticated', ErrorCode::UNAUTHENTICATED, null, 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        if ($user->hasRole(UserRole::ADMIN->value)) {
            return $next($request);
        }

        if (! $user->hasRole($roles)) {
            throw new ApiException(
                'This action is unauthorized for your role',
                ErrorCode::UNAUTHORIZED,
                ['required_roles' => $roles, 'current_roles' => $user->getRoleNames()->all()],
                403
            );
        }

        return $next($request);
    }
}
