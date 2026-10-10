<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Catalog\StoreProductVariantRequest;
use App\Http\Requests\Catalog\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Nested variant CRUD under /products/{product}/variants. */
#[OA\Tag(name: 'Products')]
class ProductVariantController extends BaseController
{
    public function __construct(private readonly ProductService $products)
    {
    }

    #[OA\Get(path: '/products/{product}/variants', operationId: 'productVariantsIndex', summary: 'List variants of a product', tags: ['Products'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Variants', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $variants = $product->variants()->orderBy('name')->get();

        return $this->successResponse(ProductVariantResource::collection($variants)->resolve());
    }

    #[OA\Post(path: '/products/{product}/variants', operationId: 'productVariantsStore', summary: 'Create a variant (business role)', tags: ['Products'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'sku'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Light'),
                new OA\Property(property: 'sku', type: 'string', example: 'SKU-0001-L'),
                new OA\Property(property: 'barcode', type: 'string', nullable: true),
                new OA\Property(property: 'attributes', type: 'object', example: ['flavor' => 'light']),
                new OA\Property(property: 'cost_price', type: 'number', example: 8.0),
                new OA\Property(property: 'sale_price', type: 'number', example: 15.0),
                new OA\Property(property: 'stock', type: 'number', example: 30),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $variant = $this->products->createVariant($product, $request->validated());

        return $this->successResponse(
            (new ProductVariantResource($variant))->resolve(),
            'Variant created successfully',
            201
        );
    }

    #[OA\Get(path: '/products/{product}/variants/{variant}', operationId: 'productVariantsShow', summary: 'Show one variant', tags: ['Products'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'variant', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Variant', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('view', $product);

        if ($variant->product_id !== $product->id) {
            throw \App\Exceptions\ResourceNotFoundException::forModel(ProductVariant::class, $variant->id);
        }

        return $this->successResponse((new ProductVariantResource($variant))->resolve());
    }

    #[OA\Put(path: '/products/{product}/variants/{variant}', operationId: 'productVariantsUpdate', summary: 'Update a variant', tags: ['Products'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'variant', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);

        $variant = $this->products->updateVariant($product, $variant, $request->validated());

        return $this->successResponse(
            (new ProductVariantResource($variant))->resolve(),
            'Variant updated successfully'
        );
    }

    #[OA\Delete(path: '/products/{product}/variants/{variant}', operationId: 'productVariantsDestroy', summary: 'Soft-delete a variant', tags: ['Products'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'variant', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function destroy(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->products->deleteVariant($product, $variant);

        return $this->noContentResponse('Variant deleted successfully');
    }
}
