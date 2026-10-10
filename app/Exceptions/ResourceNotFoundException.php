<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Thrown when a model cannot be found (or is not visible to the current
 * tenant). Maps to 404 NOT_FOUND.
 */
class ResourceNotFoundException extends ApiException
{
    public function __construct(
        string $message = 'Resource not found',
        ?array $details = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, ErrorCode::NOT_FOUND, $details, 404, $previous);
    }

    /** Convenience factory: ResourceNotFoundException::forModel(Product::class, 42). */
    public static function forModel(string $modelClass, int|string|null $id = null): self
    {
        $name = class_basename($modelClass);

        return new self(
            $id === null
                ? "{$name} not found."
                : "{$name} [{$id}] not found."
        );
    }
}
