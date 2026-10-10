<?php

declare(strict_types=1);

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SuccessResponse',
    title: 'SuccessResponse',
    description: 'Standardized success envelope',
    required: ['success', 'message', 'data', 'meta'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Operation completed successfully'),
        new OA\Property(property: 'data', description: 'Resource, array of resources or null', type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    title: 'ErrorResponse',
    description: 'Standardized error envelope',
    required: ['success', 'message', 'error', 'meta'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
        new OA\Property(property: 'error',
            properties: [
                new OA\Property(property: 'code', type: 'string',
                    enum: ['VALIDATION_ERROR', 'UNAUTHENTICATED', 'UNAUTHORIZED', 'NOT_FOUND',
                        'CONFLICT', 'BUSINESS_LOGIC_ERROR', 'RATE_LIMIT_EXCEEDED', 'SERVER_ERROR'],
                    example: 'VALIDATION_ERROR'),
                new OA\Property(property: 'details', type: 'object',
                    example: ['email' => ['The email field is required.']]),
            ],
            type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ResponseMeta',
    title: 'ResponseMeta',
    properties: [
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2025-01-15T10:30:00.000000Z'),
        new OA\Property(property: 'request_id', type: 'string', format: 'uuid', example: '3f2504e0-4f89-11d3-9a0c-0305e82c3301'),
        new OA\Property(property: 'pagination',
            properties: [
                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                new OA\Property(property: 'total', type: 'integer', example: 42),
                new OA\Property(property: 'last_page', type: 'integer', example: 3),
                new OA\Property(property: 'from', type: 'integer', example: 1),
                new OA\Property(property: 'to', type: 'integer', example: 15),
                new OA\Property(property: 'has_more_pages', type: 'boolean', example: true),
            ],
            type: 'object'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'TokenPayload',
    title: 'TokenPayload',
    properties: [
        new OA\Property(property: 'access_token', type: 'string'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string'), example: ['business', 'sales.create']),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
final class ResponseEnvelope
{
}
