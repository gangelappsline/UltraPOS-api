<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Plans\StorePlanRequest;
use App\Http\Requests\Plans\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin')]
class AdminPlanController extends BaseController
{
    #[OA\Get(path: '/admin/plans', operationId: 'adminPlansIndex', summary: 'List ALL plans incl. inactive (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Plans', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $plans = Plan::query()
            ->withCount('subscriptions')
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('price')
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse(PlanResource::collection($plans));
    }

    #[OA\Post(path: '/admin/plans', operationId: 'adminPlansStore', summary: 'Create a plan (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'price', 'billing_cycle'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Growth'),
                new OA\Property(property: 'slug', type: 'string', nullable: true, example: 'growth'),
                new OA\Property(property: 'price', type: 'number', example: 79.00),
                new OA\Property(property: 'billing_cycle', type: 'string', enum: ['monthly', 'yearly'], example: 'monthly'),
                new OA\Property(property: 'features', type: 'array', items: new OA\Items(type: 'string'), example: ['10 users', '5000 products', 'API access']),
                new OA\Property(property: 'limits', type: 'object', example: ['users' => 10, 'products' => 5000]),
                new OA\Property(property: 'description', type: 'string', nullable: true),
            ],
        )),
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function store(StorePlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug((string) $data['name']);

        $plan = Plan::create($data);

        return $this->successResponse(
            (new PlanResource($plan))->resolve(),
            'Plan created successfully',
            201
        );
    }

    #[OA\Put(path: '/admin/plans/{id}', operationId: 'adminPlansUpdate', summary: 'Update a plan (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return $this->successResponse(
            (new PlanResource($plan->refresh()))->resolve(),
            'Plan updated successfully'
        );
    }

    #[OA\Delete(path: '/admin/plans/{id}', operationId: 'adminPlansDestroy', summary: 'Delete a plan without subscriptions (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 409, description: 'Plan in use', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->exists()) {
            return $this->errorResponse(
                'This plan has subscriptions and cannot be deleted. Deactivate it instead.',
                \App\Enums\ErrorCode::CONFLICT,
                ['subscriptions_count' => $plan->subscriptions()->count()]
            );
        }

        $plan->delete();

        return $this->noContentResponse('Plan deleted successfully');
    }
}
