<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessType;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/** PUBLIC metadata endpoint: supported business verticals. */
#[OA\Tag(name: 'System')]
class BusinessTypeController extends BaseController
{
    #[OA\Get(path: '/business-types', operationId: 'businessTypesIndex', summary: 'Supported business verticals (public)', tags: ['System'],
        security: [],
        responses: [new OA\Response(response: 200, description: 'Business types', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'message', type: 'string'),
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                properties: [
                    new OA\Property(property: 'value', type: 'string', example: 'pharmacy'),
                    new OA\Property(property: 'label', type: 'string', example: 'Pharmacy'),
                    new OA\Property(property: 'tracks_expiration_dates', type: 'boolean', example: true),
                    new OA\Property(property: 'sells_by_measure', type: 'boolean', example: false),
                ],
                type: 'object'
            )),
            new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
        ], type: 'object'))])]
    public function __invoke(): JsonResponse
    {
        $types = array_map(fn (BusinessType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'tracks_expiration_dates' => $type->tracksExpirationDates(),
            'sells_by_measure' => $type->sellsByMeasure(),
        ], BusinessType::cases());

        return $this->successResponse($types);
    }
}
