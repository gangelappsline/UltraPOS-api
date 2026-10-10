<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin')]
class AdminAuditLogController extends BaseController
{
    #[OA\Get(path: '/admin/audit-logs', operationId: 'adminAuditLogsIndex', summary: 'Global audit trail (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'business_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'action', in: 'query', schema: new OA\Schema(type: 'string', enum: ['created', 'updated', 'deleted', 'restored', 'force_deleted'])),
            new OA\Parameter(name: 'model_type', in: 'query', schema: new OA\Schema(type: 'string', example: 'App\\Models\\Product')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated audit logs', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with(['user:id,name,email', 'business:id,name'])
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', (int) $request->query('business_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->query('user_id')))
            ->when($request->query('action'), fn ($q, string $action) => $q->where('action', $action))
            ->when($request->query('model_type'), fn ($q, string $type) => $q->where('model_type', 'like', '%'.$type))
            ->when($request->query('from'), fn ($q, string $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, string $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 25))
            ->withQueryString();

        return $this->paginatedResponse(AuditLogResource::collection($logs));
    }
}
