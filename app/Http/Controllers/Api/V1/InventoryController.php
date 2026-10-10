<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Inventory')]
class InventoryController extends BaseController
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ReportService $reports,
    ) {
    }

    #[OA\Get(path: '/inventory/movements', operationId: 'inventoryMovementsIndex', summary: 'Stock ledger (paginated, filterable)', tags: ['Inventory'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['purchase', 'sale', 'adjustment', 'return', 'transfer', 'loss'])),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated movements', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function movements(Request $request): JsonResponse
    {
        $movements = InventoryMovement::query()
            ->with(['product:id,sku,name,stock', 'variant:id,name,sku', 'user:id,name'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', (int) $request->query('product_id')))
            ->when($request->query('type'), fn ($q, string $type) => $q->ofType($type))
            ->when($request->query('from'), fn ($q, string $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, string $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 25))
            ->withQueryString();

        return $this->paginatedResponse(InventoryMovementResource::collection($movements));
    }

    #[OA\Post(path: '/inventory/movements', operationId: 'inventoryMovementsStore', summary: 'Record a manual stock movement', tags: ['Inventory'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['product_id', 'type', 'quantity'],
            properties: [
                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                new OA\Property(property: 'product_variant_id', type: 'integer', nullable: true),
                new OA\Property(property: 'type', type: 'string', enum: ['adjustment', 'loss', 'transfer'], example: 'adjustment'),
                new OA\Property(property: 'quantity', type: 'number', example: -2.5, description: 'Signed delta'),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Movement recorded', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Insufficient stock / validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function storeMovement(StoreInventoryMovementRequest $request): JsonResponse
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail((int) $request->validated('product_id'));

        $movement = $this->inventory->adjust(
            $product,
            InventoryMovementType::from((string) $request->validated('type')),
            (float) $request->validated('quantity'),
            $this->user($request),
            $request->validated('notes'),
        );

        return $this->successResponse(
            [
                'movement' => (new InventoryMovementResource($movement->load('product:id,sku,name,stock')))->resolve(),
                'product' => ['id' => $product->id, 'stock' => (float) $product->refresh()->stock],
            ],
            'Inventory movement recorded successfully',
            201
        );
    }

    #[OA\Get(path: '/inventory/report', operationId: 'inventoryReport', summary: 'Inventory valuation & health report', tags: ['Inventory'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Valuation report', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function report(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->reports->inventoryValuation($this->businessId($request))
        );
    }
}
