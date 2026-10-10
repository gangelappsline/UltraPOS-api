<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\User\AssignRoleRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Users')]
class UserController extends BaseController
{
    public function __construct(private readonly UserService $users)
    {
    }

    #[OA\Get(path: '/users', operationId: 'usersIndex', summary: 'List staff of the active tenant', tags: ['Users'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string', enum: ['business', 'seller', 'customer'])),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated users', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Requires business role', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->users->list(
            $this->user($request),
            $this->activeBusiness($request),
            $request->query(),
        );

        return $this->paginatedResponse(UserResource::collection($paginator));
    }

    #[OA\Post(path: '/users', operationId: 'usersStore', summary: 'Create a staff member (business owner)', tags: ['Users'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'role'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Carlos Cashier'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'carlos@store.test'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass1'),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'role', type: 'string', enum: ['business', 'seller', 'customer'], example: 'seller'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'User created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($this->activeBusiness($request), $request->validated());

        return $this->successResponse(
            (new UserResource($user))->resolve(),
            'User created successfully',
            201
        );
    }

    #[OA\Get(path: '/users/{id}', operationId: 'usersShow', summary: 'Show one user of the tenant', tags: ['Users'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'User', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Not found / other tenant', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->successResponse(
            (new UserResource($user->load('roles', 'businesses')))->resolve()
        );
    }

    #[OA\Put(path: '/users/{id}', operationId: 'usersUpdate', summary: 'Update a staff member', tags: ['Users'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->users->update($user, $request->validated());

        return $this->successResponse(
            (new UserResource($user))->resolve(),
            'User updated successfully'
        );
    }

    #[OA\Delete(path: '/users/{id}', operationId: 'usersDestroy', summary: 'Soft-delete a staff member', tags: ['Users'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Business rule violation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($this->user($request), $user);

        return $this->noContentResponse('User deleted successfully');
    }

    #[OA\Post(path: '/users/{id}/assign-role', operationId: 'usersAssignRole', summary: 'Assign a contextual role to a user', tags: ['Users'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['role'],
            properties: [new OA\Property(property: 'role', type: 'string', enum: ['admin', 'business', 'seller', 'customer'], example: 'seller')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Role assigned', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        $this->authorize('assignRole', $user);

        // Only admins may grant the global admin role.
        if ($request->validated('role') === 'admin' && ! $this->user($request)->isAdmin()) {
            return $this->errorResponse('Only administrators can grant the admin role.', \App\Enums\ErrorCode::UNAUTHORIZED);
        }

        $user = $this->users->assignRole($user, (string) $request->validated('role'));

        return $this->successResponse(
            (new UserResource($user))->resolve(),
            'Role assigned successfully'
        );
    }

    #[OA\Post(path: '/users/{id}/toggle-status', operationId: 'usersToggleStatus', summary: 'Enable/disable a user', tags: ['Users'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Status toggled', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Business rule violation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        $this->authorize('toggleStatus', $user);

        $user = $this->users->toggleStatus($this->user($request), $user);

        return $this->successResponse(
            (new UserResource($user))->resolve(),
            $user->is_active ? 'User activated successfully' : 'User deactivated successfully'
        );
    }
}
