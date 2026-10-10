<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Support\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

/**
 * Global exception handler for the API. Registered from bootstrap/app.php
 * (Laravel 12 style) and formats EVERY throwable into the standard envelope:
 *
 * { success:false, message, error:{code, details}, meta:{timestamp, request_id} }
 */
final class Handler
{
    use ApiResponse;

    public static function register(Exceptions $exceptions): void
    {
        // Never expose stack traces in production.
        $exceptions->dontReportDuplicates();

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! self::shouldRenderJson($e, $request)) {
                return null;
            }

            return self::make($e, $request);
        });
    }

    /** The API always answers in JSON; web errors stay on the default renderer. */
    private static function shouldRenderJson(Throwable $e, Request $request): bool
    {
        return $request->is('api/*')
            || $request->is('oauth/*')
            || $request->expectsJson();
    }

    /** Map any throwable to the standardized error envelope. */
    private static function make(Throwable $e, Request $request): JsonResponse
    {
        $handler = new self;

        return match (true) {
            $e instanceof ApiException => $handler->errorResponse(
                $e->getMessage(),
                $e->errorCode,
                $e->details,
                $e->status()
            ),

            $e instanceof ValidationException => $handler->errorResponse(
                'Validation failed',
                ErrorCode::VALIDATION_ERROR,
                ['fields' => $e->errors()],
                422
            ),

            $e instanceof AuthenticationException => $handler->errorResponse(
                $e->getMessage() ?: 'Unauthenticated',
                ErrorCode::UNAUTHENTICATED,
                null,
                401
            ),

            $e instanceof AuthorizationException,
            $e instanceof UnauthorizedException => $handler->errorResponse(
                'This action is unauthorized',
                ErrorCode::UNAUTHORIZED,
                $e instanceof UnauthorizedException
                    ? [
                        'required_roles' => $e->getRequiredRoles(),
                        'required_permissions' => $e->getRequiredPermissions(),
                    ]
                    : null,
                403
            ),

            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => $handler->errorResponse(
                $e instanceof ModelNotFoundException
                    ? class_basename($e->getModel()).' not found'
                    : 'Endpoint not found',
                ErrorCode::NOT_FOUND,
                null,
                404
            ),

            $e instanceof MethodNotAllowedHttpException => $handler->errorResponse(
                sprintf('Method %s not allowed for this endpoint', $request->method()),
                ErrorCode::NOT_FOUND,
                null,
                405
            ),

            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 429 => $handler->errorResponse(
                'Too many requests, please try again later',
                ErrorCode::RATE_LIMIT_EXCEEDED,
                ['retry_after_seconds' => (int) ($e->getHeaders()['Retry-After'] ?? 60)],
                429
            ),

            $e instanceof HttpExceptionInterface => $handler->errorResponse(
                $e->getMessage() ?: ErrorCode::SERVER_ERROR->defaultMessage(),
                ErrorCode::SERVER_ERROR,
                null,
                $e->getStatusCode()
            ),

            $e instanceof QueryException && self::isUniqueConstraintViolation($e) => $handler->errorResponse(
                'A record with the same unique values already exists',
                ErrorCode::CONFLICT,
                ['sql_state' => $e->errorInfo[0] ?? null],
                409
            ),

            default => $handler->errorResponse(
                config('app.debug')
                    ? $e->getMessage()
                    : ErrorCode::SERVER_ERROR->defaultMessage(),
                ErrorCode::SERVER_ERROR,
                config('app.debug')
                    ? [
                        'exception' => $e::class,
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]
                    : null,
                500
            ),
        };
    }

    /** MySQL 1062 / SQLSTATE 23000 / SQLite UNIQUE constraint failed. */
    private static function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        return $sqlState === '23000'
            || $driverCode === 1062
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
