<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use RuntimeException;
use Throwable;

/**
 * Base class for every "expected" API error. Carries the standardized
 * ErrorCode plus optional machine-readable details.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        string $message,
        public readonly ErrorCode $errorCode = ErrorCode::SERVER_ERROR,
        public readonly ?array $details = null,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode->httpStatus(), $previous);
    }

    /** Effective HTTP status for this exception. */
    public function status(): int
    {
        return $this->statusCode ?? $this->errorCode->httpStatus();
    }
}
