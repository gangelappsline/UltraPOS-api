<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Http\Requests\Purchases\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Purchases')]
class PurchaseController extends BaseController
{
    public function __construct(private readonly PurchaseService $purchases)
    {
    }

    #[OA\Get(path: '/purchases', operationId: 'purchasesIndex', summary: 'List purchase orders (business role)', tags: ['Purchases'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['draft', 'ordered', 'received', 'cancelled'])),
            new OA\Parameter(name: 'supplier_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated purchases', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'user:id,name'])
            ->withCount('items')
            ->when($request->query('status'), fn ($q, string $status) => $q->status($status))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', (int) $request->query('supplier_id')))
            ->when($request->query('from'), fn ($q, string $from) => $q->whereDate('purchased_at', '>=', $from))
            ->when($request->query('to'), fn ($q, string $to) => $q->whereDate('purchased_at', '<=', $to))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(PurchaseResource::collection($purchases));
    }

    #[OA\Post(path: '/purchases', operationId: 'purchasesStore', summary: 'Create a purchase order', tags: ['Purchases'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['items'],
            properties: [
                new OA\Property(property: 'supplier_id', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'status', type: 'string', enum: ['draft', 'ordered'], example: 'ordered'),
                new OA\Property(property: 'discount', type: 'number', example: 0),
                new OA\Property(property: 'purchased_at', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    required: ['product_id', 'quantity'],
                    properties: [
                        new OA\Property(property: 'product_id', type: 'integer', example: 1),
                        new OA\Property(property: 'quantity', type: 'number', example: 50),
                        new OA\Property(property: 'unit_cost', type: 'number', nullable: true, example: 8.2),
                    ],
                    type: 'object'
                ), example: [['product_id' => 1, 'quantity' => 50, 'unit_cost' => 8.2]]),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->purchases->create(
            $this->user($request),
            $this->businessId($request),
            $request->validated(),
        );

        return $this->successResponse(
            (new PurchaseResource($purchase))->resolve(),
            'Purchase created successfully',
            201
        );
    }

    #[OA\Get(path: '/purchases/{id}', operationId: 'purchasesShow', summary: 'Show one purchase with items', tags: ['Purchases'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Purchase', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Purchase $purchase): JsonResponse
    {
        return $this->successResponse(
            (new PurchaseResource($purchase->load(['items.product', 'supplier', 'user', 'payments'])))->resolve()
        );
    }

    #[OA\Put(path: '/purchases/{id}', operationId: 'purchasesUpdate', summary: 'Update a draft/ordered purchase', tags: ['Purchases'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Not editable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function update(UpdatePurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        $purchase = $this->purchases->update($purchase, $request->validated());

        return $this->successResponse(
            (new PurchaseResource($purchase))->resolve(),
            'Purchase updated successfully'
        );
    }

    #[OA\Delete(path: '/purchases/{id}', operationId: 'purchasesDestroy', summary: 'Soft-delete a draft purchase', tags: ['Purchases'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Only draft purchases can be deleted', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function destroy(Purchase $purchase): JsonResponse
    {
        if ($purchase->status !== \App\Enums\PurchaseStatus::DRAFT) {
            return $this->errorResponse(
                'Only draft purchases can be deleted. Cancel it instead.',
                \App\Enums\ErrorCode::BUSINESS_LOGIC_ERROR
            );
        }

        $purchase->delete();

        return $this->noContentResponse('Purchase deleted successfully');
    }

    #[OA\Post(path: '/purchases/{id}/receive', operationId: 'purchasesReceive', summary: 'Receive the purchase (stock IN + ledger)', tags: ['Purchases'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Received', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Already received/cancelled', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function receive(Request $request, Purchase $purchase): JsonResponse
    {
        $purchase = $this->purchases->receive($purchase, $this->user($request));

        return $this->successResponse(
            (new PurchaseResource($purchase))->resolve(),
            'Purchase received successfully. Stock updated.'
        );
    }

    #[OA\Post(path: '/purchases/{id}/cancel', operationId: 'purchasesCancel', summary: 'Cancel a draft/ordered purchase', tags: ['Purchases'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reason'],
            properties: [new OA\Property(property: 'reason', type: 'string', example: 'Supplier out of stock')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Cancelled', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Not cancellable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function cancel(Request $request, Purchase $purchase): JsonResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];

        $purchase = $this->purchases->cancel($purchase, $this->user($request), $reason);

        return $this->successResponse(
            (new PurchaseResource($purchase))->resolve(),
            'Purchase cancelled successfully'
        );
    }
}
