<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Sales\CancelSaleRequest;
use App\Http\Requests\Sales\RefundSaleRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Sales')]
class SaleController extends BaseController
{
    public function __construct(private readonly SaleService $sales)
    {
    }

    #[OA\Get(path: '/sales', operationId: 'salesIndex', summary: 'List sales (paginated, filterable)', tags: ['Sales'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'completed', 'cancelled', 'refunded'])),
            new OA\Parameter(name: 'payment_status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['unpaid', 'partial', 'paid'])),
            new OA\Parameter(name: 'sale_type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pos', 'online', 'phone'])),
            new OA\Parameter(name: 'customer_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated sales', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $sales = Sale::query()
            ->with(['customer:id,name', 'user:id,name'])
            ->withCount('items')
            ->when($request->query('status'), fn ($q, string $status) => $q->status($status))
            ->when($request->query('payment_status'), fn ($q, string $ps) => $q->where('payment_status', $ps))
            ->when($request->query('sale_type'), fn ($q, string $type) => $q->where('sale_type', $type))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', (int) $request->query('customer_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->query('user_id')))
            ->when($request->query('from'), fn ($q, string $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, string $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(SaleResource::collection($sales));
    }

    #[OA\Post(path: '/sales', operationId: 'salesStore', summary: 'Create a POS sale (stock-checked, server-side totals)', tags: ['Sales'],
        description: 'Atomic sale creation: locks products, validates stock, computes totals server-side, '
            .'records the payment (with change for cash), consumes stock, writes the inventory ledger, '
            .'updates customer loyalty/credit and queues the receipt email.',
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['items'],
            properties: [
                new OA\Property(property: 'customer_id', type: 'integer', nullable: true, example: 3),
                new OA\Property(property: 'sale_type', type: 'string', enum: ['pos', 'online', 'phone'], default: 'pos'),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
                new OA\Property(property: 'discount', type: 'number', example: 0, description: 'Global sale discount (absolute)'),
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    required: ['product_id', 'quantity'],
                    properties: [
                        new OA\Property(property: 'product_id', type: 'integer', example: 1),
                        new OA\Property(property: 'product_variant_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'quantity', type: 'number', example: 2),
                        new OA\Property(property: 'discount', type: 'number', nullable: true, example: 0),
                    ],
                    type: 'object'
                ), example: [['product_id' => 1, 'quantity' => 2], ['product_id' => 7, 'quantity' => 1.5]]),
                new OA\Property(property: 'payment', type: 'object', nullable: true, properties: [
                    new OA\Property(property: 'method', type: 'string', enum: ['cash', 'card', 'transfer', 'credit', 'mixed'], example: 'cash'),
                    new OA\Property(property: 'amount', type: 'number', example: 50),
                    new OA\Property(property: 'reference', type: 'string', nullable: true),
                ]),
                new OA\Property(property: 'pay_later', type: 'boolean', example: false, description: 'Credit sale — requires customer_id'),
                new OA\Property(property: 'send_receipt', type: 'boolean', example: true),
                new OA\Property(property: 'receipt_email', type: 'string', nullable: true, example: 'john@mail.test'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Sale created & completed', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Sale created successfully'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Sale'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
            ], type: 'object')),
            new OA\Response(response: 422, description: 'Insufficient stock / credit limit / validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $sale = $this->sales->create($this->user($request), $request->validated());

        return $this->successResponse(
            (new SaleResource($sale))->resolve(),
            'Sale created successfully',
            201
        );
    }

    #[OA\Get(path: '/sales/daily-summary', operationId: 'salesDailySummary', summary: 'Daily aggregate for a date', tags: ['Sales'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2025-01-15')],
        responses: [new OA\Response(response: 200, description: 'Daily summary', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse((string) $request->query('date')) : Carbon::today();

        return $this->successResponse(
            $this->sales->dailySummary($this->businessId($request), $date)
        );
    }

    #[OA\Get(path: '/sales/{id}', operationId: 'salesShow', summary: 'Show one sale with items and payments', tags: ['Sales'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Sale', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Sale'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
            ], type: 'object')),
            new OA\Response(response: 404, description: 'Not found / other tenant', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function show(Request $request, Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return $this->successResponse(
            (new SaleResource($sale->load(['items.product', 'items.variant', 'payments', 'customer', 'user', 'business'])))->resolve()
        );
    }

    #[OA\Post(path: '/sales/{id}/cancel', operationId: 'salesCancel', summary: 'Cancel a sale (restock + refund, owner only)', tags: ['Sales'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reason'],
            properties: [
                new OA\Property(property: 'reason', type: 'string', example: 'Customer changed their mind'),
                new OA\Property(property: 'restock', type: 'boolean', default: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Cancelled', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 403, description: 'Sellers cannot cancel sales', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Sale already final', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function cancel(CancelSaleRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('cancel', $sale);

        $sale = $this->sales->cancel(
            $sale,
            $this->user($request),
            (string) $request->validated('reason'),
            (bool) $request->validated('restock', true),
        );

        return $this->successResponse(
            (new SaleResource($sale))->resolve(),
            'Sale cancelled successfully'
        );
    }

    #[OA\Post(path: '/sales/{id}/refund', operationId: 'salesRefund', summary: 'Full/partial refund of a completed sale', tags: ['Sales'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['reason'],
            properties: [
                new OA\Property(property: 'reason', type: 'string', example: 'Damaged product'),
                new OA\Property(property: 'amount', type: 'number', nullable: true, example: 15.00),
                new OA\Property(property: 'items', type: 'array', nullable: true, items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'sale_item_id', type: 'integer', example: 11),
                        new OA\Property(property: 'quantity', type: 'number', example: 1),
                    ],
                    type: 'object'
                )),
                new OA\Property(property: 'refund_method', type: 'string', nullable: true, enum: ['cash', 'card', 'transfer', 'credit', 'mixed']),
                new OA\Property(property: 'restock', type: 'boolean', default: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Refunded', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Nothing to refund / exceeds paid amount', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function refund(RefundSaleRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('refund', $sale);

        $sale = $this->sales->refund($sale, $this->user($request), $request->validated());

        return $this->successResponse(
            (new SaleResource($sale))->resolve(),
            'Sale refunded successfully'
        );
    }

    #[OA\Get(path: '/sales/{id}/receipt', operationId: 'salesReceipt', summary: 'Structured receipt payload (print-ready JSON)', tags: ['Sales'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Receipt data', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function receipt(Request $request, Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return $this->successResponse($this->sales->receiptData($sale));
    }

    #[OA\Post(path: '/sales/{id}/send-receipt', operationId: 'salesSendReceipt', summary: 'Email the receipt (queued)', tags: ['Sales'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'john@mail.test'),
        ], type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Receipt queued', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'No email available', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function sendReceipt(Request $request, Sale $sale): JsonResponse
    {
        $this->authorize('sendReceipt', $sale);

        $validated = $request->validate(['email' => ['nullable', 'email:rfc', 'max:190']]);
        $to = $this->sales->sendReceipt($sale, $validated['email'] ?? null);

        return $this->successResponse(['sent_to' => $to], 'Receipt email queued successfully');
    }
}
