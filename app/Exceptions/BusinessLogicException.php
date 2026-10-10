<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Throwable;

/**
 * Thrown when a business rule is violated (insufficient stock, credit limit
 * exceeded, register already open, ...). Maps to 422 BUSINESS_LOGIC_ERROR.
 */
class BusinessLogicException extends ApiException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        string $message = 'Business rule violation',
        ?array $details = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, ErrorCode::BUSINESS_LOGIC_ERROR, $details, 422, $previous);
    }
}
