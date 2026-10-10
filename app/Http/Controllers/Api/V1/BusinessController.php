<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Business\StoreBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessSettingsRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\BusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Businesses')]
class BusinessController extends BaseController
{
    public function __construct(private readonly BusinessService $businesses)
    {
    }

    #[OA\Get(path: '/businesses', operationId: 'businessesIndex', summary: 'List businesses the caller owns/belongs to (admins: all)', tags: ['Businesses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'business_type', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated businesses', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->businesses->list($this->user($request), $request->query());

        return $this->paginatedResponse(BusinessResource::collection($paginator));
    }

    #[OA\Post(path: '/businesses', operationId: 'businessesStore', summary: 'Create a business (admin only)', tags: ['Businesses'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'business_type'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Farmacia Santa Fe'),
                new OA\Property(property: 'business_type', type: 'string', example: 'pharmacy'),
                new OA\Property(property: 'owner_id', type: 'integer', nullable: true, example: 5),
                new OA\Property(property: 'currency', type: 'string', example: 'MXN'),
                new OA\Property(property: 'timezone', type: 'string', example: 'America/Mexico_City'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Business created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $this->authorize('create', Business::class);

        $business = $this->businesses->create($this->user($request), $request->validated());

        return $this->successResponse(
            (new BusinessResource($business))->resolve(),
            'Business created successfully',
            201
        );
    }

    #[OA\Get(path: '/businesses/{id}', operationId: 'businessesShow', summary: 'Show one business', tags: ['Businesses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Business', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Not your tenant', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function show(Request $request, Business $business): JsonResponse
    {
        $this->authorize('view', $business);

        return $this->successResponse(
            (new BusinessResource($business->load(['owner', 'currentSubscription.plan'])))->resolve()
        );
    }

    #[OA\Put(path: '/businesses/{id}', operationId: 'businessesUpdate', summary: 'Update a business', tags: ['Businesses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function update(UpdateBusinessRequest $request, Business $business): JsonResponse
    {
        $this->authorize('update', $business);

        // Non-admins may not self-deactivate through this endpoint.
        $payload = $request->validated();
        if (! $this->user($request)->isAdmin()) {
            unset($payload['is_active']);
        }

        $business = $this->businesses->update($business, $payload);

        return $this->successResponse(
            (new BusinessResource($business))->resolve(),
            'Business updated successfully'
        );
    }

    #[OA\Delete(path: '/businesses/{id}', operationId: 'businessesDestroy', summary: 'Soft-delete a business (owner/admin)', tags: ['Businesses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function destroy(Request $request, Business $business): JsonResponse
    {
        $this->authorize('delete', $business);

        $this->businesses->delete($business);

        return $this->noContentResponse('Business deleted successfully');
    }

    #[OA\Get(path: '/businesses/{id}/settings', operationId: 'businessesSettings', summary: 'Get tenant settings grouped by group', tags: ['Businesses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Settings map', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function settings(Request $request, Business $business): JsonResponse
    {
        $this->authorize('manageSettings', $business);

        return $this->successResponse($this->businesses->settings($business));
    }

    #[OA\Put(path: '/businesses/{id}/settings', operationId: 'businessesUpdateSettings', summary: 'Bulk upsert tenant settings', tags: ['Businesses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['settings'],
            properties: [
                new OA\Property(property: 'settings', type: 'object', example: ['receipt.footer' => 'Thank you!', 'tax.default_rate' => 16]),
                new OA\Property(property: 'group', type: 'string', example: 'general'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Settings saved', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function updateSettings(UpdateBusinessSettingsRequest $request, Business $business): JsonResponse
    {
        $this->authorize('manageSettings', $business);

        $settings = $this->businesses->updateSettings(
            $business,
            $request->validated('settings'),
            (string) $request->input('group', 'general'),
        );

        return $this->successResponse($settings, 'Settings updated successfully');
    }
}
