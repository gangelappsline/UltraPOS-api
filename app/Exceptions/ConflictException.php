<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Throwable;

/**
 * Thrown on uniqueness / state conflicts (duplicated SKU, register already
 * open, ...). Maps to 409 CONFLICT.
 */
class ConflictException extends ApiException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        string $message = 'Resource conflict',
        ?array $details = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, ErrorCode::CONFLICT, $details, 409, $previous);
    }
}
