<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/** PUBLIC plans catalog (marketing/pricing page consumption). */
#[OA\Tag(name: 'Plans')]
class PlanController extends BaseController
{
    #[OA\Get(path: '/plans', operationId: 'plansIndex', summary: 'List active plans (public)', tags: ['Plans'],
        security: [],
        responses: [new OA\Response(response: 200, description: 'Plans', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(): JsonResponse
    {
        $plans = Plan::query()->active()->orderBy('price')->get();

        return $this->successResponse(PlanResource::collection($plans)->resolve());
    }

    #[OA\Get(path: '/plans/{id}', operationId: 'plansShow', summary: 'Show one active plan (public)', tags: ['Plans'],
        security: [],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Plan', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Not found or inactive', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function show(Plan $plan): JsonResponse
    {
        abort_unless($plan->is_active, 404, 'Plan not found.');

        return $this->successResponse((new PlanResource($plan))->resolve());
    }
}
