<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Catalog\AdjustStockRequest;
use App\Http\Requests\Catalog\ImportProductsRequest;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[OA\Tag(name: 'Products')]
class ProductController extends BaseController
{
    public function __construct(
        private readonly ProductService $products,
        private readonly InventoryService $inventory,
    ) {
    }

    #[OA\Get(path: '/products', operationId: 'productsIndex', summary: 'List/search products of the tenant', tags: ['Products'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Free text on name/sku/barcode', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'brand_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'is_featured', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'low_stock', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'in_stock', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'min_price', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'max_price', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'expiring_days', in: 'query', description: 'Products expiring within N days (pharmacies/groceries)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'sku', 'sale_price', 'stock', 'created_at'])),
            new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated products', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->products->list($this->businessId($request), $request->query());

        return $this->paginatedResponse(ProductResource::collection($paginator));
    }

    #[OA\Post(path: '/products', operationId: 'productsStore', summary: 'Create a product (business role)', tags: ['Products'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'sku', 'cost_price', 'sale_price'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Cola 600ml'),
                new OA\Property(property: 'sku', type: 'string', example: 'SKU-0001'),
                new OA\Property(property: 'barcode', type: 'string', nullable: true, example: '7501234567890'),
                new OA\Property(property: 'cost_price', type: 'number', example: 8.5),
                new OA\Property(property: 'sale_price', type: 'number', example: 15.0),
                new OA\Property(property: 'tax_rate', type: 'number', example: 16),
                new OA\Property(property: 'stock', type: 'number', example: 100),
                new OA\Property(property: 'min_stock', type: 'number', example: 10),
                new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                new OA\Property(property: 'brand_id', type: 'integer', nullable: true),
                new OA\Property(property: 'unit_id', type: 'integer', nullable: true),
                new OA\Property(property: 'metadata', type: 'object', nullable: true, example: ['flavor' => 'cola']),
                new OA\Property(property: 'variants', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Light'),
                        new OA\Property(property: 'sku', type: 'string', example: 'SKU-0001-L'),
                        new OA\Property(property: 'sale_price', type: 'number', example: 15.0),
                        new OA\Property(property: 'stock', type: 'number', example: 20),
                    ],
                    type: 'object'
                )),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation / duplicated SKU', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->products->create($this->businessId($request), $request->validated());

        return $this->successResponse(
            (new ProductResource($product))->resolve(),
            'Product created successfully',
            201
        );
    }

    #[OA\Get(path: '/products/barcode/{barcode}', operationId: 'productsBarcode', summary: 'Find a product (or variant) by barcode', tags: ['Products'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'barcode', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Product', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Barcode not found in tenant', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function barcode(Request $request, string $barcode): JsonResponse
    {
        $product = $this->products->findByBarcode($this->businessId($request), $barcode);

        return $this->successResponse((new ProductResource($product))->resolve());
    }

    #[OA\Get(path: '/products/low-stock', operationId: 'productsLowStock', summary: 'Products at/below minimum stock', tags: ['Products'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Low stock products', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function lowStock(Request $request): JsonResponse
    {
        $products = $this->products->lowStock($this->businessId($request));

        return $this->successResponse(ProductResource::collection($products)->resolve());
    }

    #[OA\Get(path: '/products/export', operationId: 'productsExport', summary: 'Export tenant catalog as CSV', tags: ['Products'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'CSV stream', content: new OA\MediaType(mediaType: 'text/csv'))])]
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Product::class);

        return $this->products->exportCsv($this->businessId($request));
    }

    #[OA\Post(path: '/products/import', operationId: 'productsImport', summary: 'Bulk import products from CSV (business role)', tags: ['Products'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(properties: [
                new OA\Property(property: 'file', type: 'string', format: 'binary'),
                new OA\Property(property: 'on_duplicate', type: 'string', enum: ['skip', 'update', 'fail'], default: 'update'),
                new OA\Property(property: 'async', type: 'boolean', description: 'Queue the import instead of processing inline', default: false),
            ])
        )),
        responses: [
            new OA\Response(response: 200, description: 'Import result statistics', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 202, description: 'Import queued', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Invalid file', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function import(ImportProductsRequest $request): JsonResponse
    {
        $this->authorize('import', Product::class);

        $businessId = $this->businessId($request);
        $onDuplicate = (string) $request->validated('on_duplicate', 'update');
        $file = $request->file('file');
        $async = $request->boolean('async');

        if ($async) {
            $storedPath = $file->store('imports/products', 'local');
            ProcessProductImport::dispatch($businessId, $storedPath, $onDuplicate, $this->user($request)->id);

            return $this->successResponse(
                ['queued' => true],
                'Import queued. You will receive a notification with the result.',
                202
            );
        }

        $result = $this->products->importCsv($businessId, $file->getRealPath(), $onDuplicate);

        return $this->successResponse($result, 'Import completed');
    }

    #[OA\Get(path: '/products/{id}', operationId: 'productsShow', summary: 'Show one product with variants', tags: ['Products'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Product', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Not found / other tenant', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->successResponse(
            (new ProductResource($product->load(['category', 'brand', 'unit', 'variants'])))->resolve()
        );
    }

    #[OA\Put(path: '/products/{id}', operationId: 'productsUpdate', summary: 'Update a product (business role)', tags: ['Products'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->products->update($product, $request->validated());

        return $this->successResponse(
            (new ProductResource($product))->resolve(),
            'Product updated successfully'
        );
    }

    #[OA\Delete(path: '/products/{id}', operationId: 'productsDestroy', summary: 'Soft-delete a product (business role)', tags: ['Products'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted (soft)', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->products->delete($product);

        return $this->noContentResponse('Product deleted successfully');
    }

    #[OA\Post(path: '/products/{id}/adjust-stock', operationId: 'productsAdjustStock', summary: 'Manual stock adjustment (business/seller)', tags: ['Products'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['type', 'quantity'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['adjustment', 'loss', 'transfer'], example: 'adjustment'),
                new OA\Property(property: 'quantity', type: 'number', description: 'Signed delta (+10 restock / −3 loss)', example: 10),
                new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Cycle count correction'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Stock adjusted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Insufficient stock / validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function adjustStock(AdjustStockRequest $request, Product $product): JsonResponse
    {
        $this->authorize('adjustStock', $product);

        $movement = $this->inventory->adjust(
            $product,
            InventoryMovementType::from((string) $request->validated('type')),
            (float) $request->validated('quantity'),
            $this->user($request),
            $request->validated('notes'),
        );

        return $this->successResponse(
            [
                'product' => (new ProductResource($product->refresh()))->resolve(),
                'movement' => (new \App\Http\Resources\InventoryMovementResource($movement))->resolve(),
            ],
            'Stock adjusted successfully'
        );
    }
}
