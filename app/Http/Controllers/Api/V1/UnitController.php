<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Catalog\StoreUnitRequest;
use App\Http\Requests\Catalog\UpdateUnitRequest;
use App\Http\Resources\UnitOfMeasureResource;
use App\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Units')]
class UnitController extends BaseController
{
    #[OA\Get(path: '/units', operationId: 'unitsIndex', summary: 'List units of measure', tags: ['Units'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['unit', 'weight', 'volume', 'length'])),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Units', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $units = UnitOfMeasure::query()
            ->when($request->query('type'), fn ($q, string $type) => $q->where('type', $type))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 50))
            ->withQueryString();

        return $this->paginatedResponse(UnitOfMeasureResource::collection($units));
    }

    #[OA\Post(path: '/units', operationId: 'unitsStore', summary: 'Create a unit of measure (business role)', tags: ['Units'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'abbreviation', 'type'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Kilogram'),
                new OA\Property(property: 'abbreviation', type: 'string', example: 'kg'),
                new OA\Property(property: 'type', type: 'string', enum: ['unit', 'weight', 'volume', 'length'], example: 'weight'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = UnitOfMeasure::create($request->validated());

        return $this->successResponse(
            (new UnitOfMeasureResource($unit))->resolve(),
            'Unit created successfully',
            201
        );
    }

    #[OA\Get(path: '/units/{id}', operationId: 'unitsShow', summary: 'Show one unit', tags: ['Units'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Unit', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(UnitOfMeasure $unit): JsonResponse
    {
        return $this->successResponse((new UnitOfMeasureResource($unit))->resolve());
    }

    #[OA\Put(path: '/units/{id}', operationId: 'unitsUpdate', summary: 'Update a unit', tags: ['Units'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function update(UpdateUnitRequest $request, UnitOfMeasure $unit): JsonResponse
    {
        $unit->update($request->validated());

        return $this->successResponse(
            (new UnitOfMeasureResource($unit->refresh()))->resolve(),
            'Unit updated successfully'
        );
    }

    #[OA\Delete(path: '/units/{id}', operationId: 'unitsDestroy', summary: 'Delete a unit (hard delete — units keep no history)', tags: ['Units'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Unit in use', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function destroy(UnitOfMeasure $unit): JsonResponse
    {
        if ($unit->products()->exists()) {
            return $this->errorResponse(
                'This unit is assigned to products and cannot be deleted. Deactivate it instead.',
                \App\Enums\ErrorCode::CONFLICT,
                ['products_count' => $unit->products()->count()]
            );
        }

        $unit->delete();

        return $this->noContentResponse('Unit deleted successfully');
    }
}
