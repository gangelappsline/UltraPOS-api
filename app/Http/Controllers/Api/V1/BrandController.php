<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Brands')]
class BrandController extends BaseController
{
    #[OA\Get(path: '/brands', operationId: 'brandsIndex', summary: 'List brands (paginated)', tags: ['Brands'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated brands', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query()
            ->withCount('products')
            ->when($request->query('search'), fn ($q, string $term) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(BrandResource::collection($brands));
    }

    #[OA\Post(path: '/brands', operationId: 'brandsStore', summary: 'Create a brand (business role)', tags: ['Brands'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Acme'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'logo', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $data = $request->validated();

        $brand = Brand::create([
            ...$data,
            'slug' => $this->uniqueSlug((string) $data['name']),
        ]);

        return $this->successResponse(
            (new BrandResource($brand))->resolve(),
            'Brand created successfully',
            201
        );
    }

    #[OA\Get(path: '/brands/{id}', operationId: 'brandsShow', summary: 'Show one brand', tags: ['Brands'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Brand', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Brand $brand): JsonResponse
    {
        return $this->successResponse((new BrandResource($brand->loadCount('products')))->resolve());
    }

    #[OA\Put(path: '/brands/{id}', operationId: 'brandsUpdate', summary: 'Update a brand', tags: ['Brands'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $brand->name) {
            $data['slug'] = $this->uniqueSlug((string) $data['name'], $brand->id);
        }

        $brand->update($data);

        return $this->successResponse(
            (new BrandResource($brand->refresh()))->resolve(),
            'Brand updated successfully'
        );
    }

    #[OA\Delete(path: '/brands/{id}', operationId: 'brandsDestroy', summary: 'Soft-delete a brand', tags: ['Brands'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return $this->noContentResponse('Brand deleted successfully');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $suffix = 1;

        while (Brand::withTrashed()
            ->where('business_id', auth('api')->user()?->active_business_id)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
