<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Suppliers\StoreSupplierRequest;
use App\Http\Requests\Suppliers\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Suppliers')]
class SupplierController extends BaseController
{
    #[OA\Get(path: '/suppliers', operationId: 'suppliersIndex', summary: 'List suppliers (business role)', tags: ['Suppliers'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated suppliers', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->search($request->query('search'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->withCount('purchases')
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(SupplierResource::collection($suppliers));
    }

    #[OA\Post(path: '/suppliers', operationId: 'suppliersStore', summary: 'Create a supplier', tags: ['Suppliers'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Distribuidora Central'),
                new OA\Property(property: 'contact_name', type: 'string', nullable: true),
                new OA\Property(property: 'email', type: 'string', nullable: true),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'tax_id', type: 'string', nullable: true),
            ],
        )),
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return $this->successResponse(
            (new SupplierResource($supplier))->resolve(),
            'Supplier created successfully',
            201
        );
    }

    #[OA\Get(path: '/suppliers/{id}', operationId: 'suppliersShow', summary: 'Show one supplier', tags: ['Suppliers'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Supplier', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Supplier $supplier): JsonResponse
    {
        return $this->successResponse(
            (new SupplierResource($supplier->loadCount('purchases')))->resolve()
        );
    }

    #[OA\Put(path: '/suppliers/{id}', operationId: 'suppliersUpdate', summary: 'Update a supplier', tags: ['Suppliers'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return $this->successResponse(
            (new SupplierResource($supplier->refresh()))->resolve(),
            'Supplier updated successfully'
        );
    }

    #[OA\Delete(path: '/suppliers/{id}', operationId: 'suppliersDestroy', summary: 'Soft-delete a supplier', tags: ['Suppliers'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return $this->noContentResponse('Supplier deleted successfully');
    }
}
