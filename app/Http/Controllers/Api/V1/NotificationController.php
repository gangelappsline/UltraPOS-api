<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Notifications')]
class NotificationController extends BaseController
{
    #[OA\Get(path: '/notifications', operationId: 'notificationsIndex', summary: 'In-app notifications of the authenticated user', tags: ['Notifications'],
        security: [['passport' => []], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'unread_only', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated notifications', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $notifications = $request->boolean('unread_only')
            ? $user->unreadNotifications()
            : $user->notifications();

        $paginator = $notifications
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(NotificationResource::collection($paginator));
    }

    #[OA\Post(path: '/notifications/{id}/read', operationId: 'notificationsRead', summary: 'Mark one notification as read', tags: ['Notifications'],
        security: [['passport' => []], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Marked as read', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 404, description: 'Notification not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->user($request)
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return $this->successResponse(
            (new NotificationResource($notification->refresh()))->resolve(),
            'Notification marked as read'
        );
    }

    #[OA\Post(path: '/notifications/read-all', operationId: 'notificationsReadAll', summary: 'Mark every notification as read', tags: ['Notifications'],
        security: [['passport' => []], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'All marked as read', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function markAllRead(Request $request): JsonResponse
    {
        $this->user($request)->unreadNotifications->markAsRead();

        return $this->successResponse(null, 'All notifications marked as read');
    }
}
