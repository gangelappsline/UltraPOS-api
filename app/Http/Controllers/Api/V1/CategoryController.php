<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Categories')]
class CategoryController extends BaseController
{
    #[OA\Get(path: '/categories', operationId: 'categoriesIndex', summary: 'List categories (paginated)', tags: ['Categories'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'parent_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated categories', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->with('parent')
            ->withCount('products')
            ->when($request->query('search'), function ($q, string $term): void {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']);
            })
            ->when($request->filled('parent_id'), fn ($q) => $q->where('parent_id', (int) $request->query('parent_id')))
            ->when($request->query('parent_id') === 'root', fn ($q) => $q->roots())
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->ordered()
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(CategoryResource::collection($categories));
    }

    #[OA\Get(path: '/categories/tree', operationId: 'categoriesTree', summary: 'Full category tree of the tenant', tags: ['Categories'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Tree of root categories with nested children', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function tree(Request $request): JsonResponse
    {
        $tree = Category::query()
            ->roots()
            ->with('allChildren')
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->ordered()
            ->get();

        return $this->successResponse(CategoryResource::collection($tree)->resolve());
    }

    #[OA\Post(path: '/categories', operationId: 'categoriesStore', summary: 'Create a category (business role)', tags: ['Categories'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Beverages'),
                new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'sort_order', type: 'integer', example: 1),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = Category::create([
            ...$data,
            'slug' => $this->uniqueSlug((string) $data['name']),
        ]);

        return $this->successResponse(
            (new CategoryResource($category->load('parent')))->resolve(),
            'Category created successfully',
            201
        );
    }

    #[OA\Get(path: '/categories/{id}', operationId: 'categoriesShow', summary: 'Show one category', tags: ['Categories'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Category', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Category $category): JsonResponse
    {
        return $this->successResponse(
            (new CategoryResource($category->load(['parent', 'children'])))->resolve()
        );
    }

    #[OA\Put(path: '/categories/{id}', operationId: 'categoriesUpdate', summary: 'Update a category', tags: ['Categories'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug((string) $data['name'], $category->id);
        }

        $category->update($data);

        return $this->successResponse(
            (new CategoryResource($category->refresh()->load('parent')))->resolve(),
            'Category updated successfully'
        );
    }

    #[OA\Delete(path: '/categories/{id}', operationId: 'categoriesDestroy', summary: 'Soft-delete a category', tags: ['Categories'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return $this->noContentResponse('Category deleted successfully');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 1;

        while (Category::withTrashed()
            ->where('business_id', auth('api')->user()?->active_business_id)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
