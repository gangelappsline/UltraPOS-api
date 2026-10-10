<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin')]
class AdminUserController extends BaseController
{
    #[OA\Get(path: '/admin/users', operationId: 'adminUsersIndex', summary: 'List ALL users across tenants (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', schema: new OA\Schema(type: 'string', enum: ['admin', 'business', 'seller', 'customer'])),
            new OA\Parameter(name: 'business_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated users', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles', 'businesses:id,name')
            ->when($request->query('search'), function ($q, string $term): void {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($qq) use ($like): void {
                    $qq->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
            })
            ->when($request->query('role'), fn ($q, string $role) => $q->role($role))
            ->when($request->filled('business_id'), fn ($q) => $q->whereHas(
                'businesses',
                fn ($bq) => $bq->whereKey((int) $request->query('business_id'))
            ))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(UserResource::collection($users));
    }
}
