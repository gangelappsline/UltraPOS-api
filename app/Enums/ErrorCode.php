<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Standardized API error codes used across the whole response envelope.
 * Each code maps to a default HTTP status and a default (i18n-ready) message.
 */
enum ErrorCode: string
{
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case NOT_FOUND = 'NOT_FOUND';
    case CONFLICT = 'CONFLICT';
    case BUSINESS_LOGIC_ERROR = 'BUSINESS_LOGIC_ERROR';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    case SERVER_ERROR = 'SERVER_ERROR';

    /** Default HTTP status code for this error kind. */
    public function httpStatus(): int
    {
        return match ($this) {
            self::VALIDATION_ERROR => 422,
            self::UNAUTHENTICATED => 401,
            self::UNAUTHORIZED => 403,
            self::NOT_FOUND => 404,
            self::CONFLICT => 409,
            self::BUSINESS_LOGIC_ERROR => 422,
            self::RATE_LIMIT_EXCEEDED => 429,
            self::SERVER_ERROR => 500,
        };
    }

    /** Default message (translatable key-friendly). */
    public function defaultMessage(): string
    {
        return match ($this) {
            self::VALIDATION_ERROR => 'Validation failed',
            self::UNAUTHENTICATED => 'Unauthenticated',
            self::UNAUTHORIZED => 'This action is unauthorized',
            self::NOT_FOUND => 'Resource not found',
            self::CONFLICT => 'Resource conflict',
            self::BUSINESS_LOGIC_ERROR => 'Business rule violation',
            self::RATE_LIMIT_EXCEEDED => 'Too many requests',
            self::SERVER_ERROR => 'Internal server error',
        };
    }
}
