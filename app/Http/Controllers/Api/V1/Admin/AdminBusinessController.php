<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\BusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin')]
class AdminBusinessController extends BaseController
{
    public function __construct(private readonly BusinessService $businesses)
    {
    }

    #[OA\Get(path: '/admin/businesses', operationId: 'adminBusinessesIndex', summary: 'List ALL tenants (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'business_type', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated businesses', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Requires admin role', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->businesses->list($this->user($request), $request->query());

        return $this->paginatedResponse(BusinessResource::collection($paginator));
    }

    #[OA\Put(path: '/admin/businesses/{id}/toggle-status', operationId: 'adminBusinessesToggle', summary: 'Enable/disable a tenant (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Status toggled', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function toggleStatus(Business $business): JsonResponse
    {
        $business = $this->businesses->toggleStatus($business);

        return $this->successResponse(
            (new BusinessResource($business))->resolve(),
            $business->is_active ? 'Business activated' : 'Business deactivated'
        );
    }
}
