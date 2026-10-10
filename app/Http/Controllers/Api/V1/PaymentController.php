<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Payments\RefundPaymentRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Payments')]
class PaymentController extends BaseController
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    #[OA\Get(path: '/payments', operationId: 'paymentsIndex', summary: 'List payments (paginated)', tags: ['Payments'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'method', in: 'query', schema: new OA\Schema(type: 'string', enum: ['cash', 'card', 'transfer', 'credit', 'mixed'])),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'completed', 'failed', 'refunded'])),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated payments', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with('user:id,name')
            ->when($request->query('method'), fn ($q, string $m) => $q->where('method', $m))
            ->when($request->query('status'), fn ($q, string $s) => $q->where('status', $s))
            ->when($request->query('from'), fn ($q, string $from) => $q->whereDate('paid_at', '>=', $from))
            ->when($request->query('to'), fn ($q, string $to) => $q->whereDate('paid_at', '<=', $to))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(PaymentResource::collection($payments));
    }

    #[OA\Post(path: '/payments', operationId: 'paymentsStore', summary: 'Register a payment for a sale/customer/purchase', tags: ['Payments'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['payable_type', 'payable_id', 'method', 'amount'],
            properties: [
                new OA\Property(property: 'payable_type', type: 'string', enum: ['sale', 'customer', 'purchase'], example: 'sale'),
                new OA\Property(property: 'payable_id', type: 'integer', example: 1),
                new OA\Property(property: 'method', type: 'string', enum: ['cash', 'card', 'transfer', 'credit', 'mixed'], example: 'card'),
                new OA\Property(property: 'amount', type: 'number', example: 34.80),
                new OA\Property(property: 'reference', type: 'string', nullable: true),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Payment registered', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Exceeds balance / already paid', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->createForDocument(
            $this->user($request),
            $request->payableClass(),
            (int) $request->validated('payable_id'),
            $request->validated(),
        );

        return $this->successResponse(
            (new PaymentResource($payment->load('user:id,name')))->resolve(),
            'Payment registered successfully',
            201
        );
    }

    #[OA\Get(path: '/payments/{id}', operationId: 'paymentsShow', summary: 'Show one payment', tags: ['Payments'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Payment', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Payment $payment): JsonResponse
    {
        return $this->successResponse(
            (new PaymentResource($payment->load('user:id,name')))->resolve()
        );
    }

    #[OA\Post(path: '/payments/{id}/refund', operationId: 'paymentsRefund', summary: 'Refund a completed payment', tags: ['Payments'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'amount', type: 'number', nullable: true, example: 10.00),
            new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'Customer returned item'),
        ], type: 'object')),
        responses: [
            new OA\Response(response: 201, description: 'Refund registered', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Not refundable', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function refund(RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $refund = $this->payments->refund(
            $payment,
            $this->user($request),
            $request->filled('amount') ? (float) $request->validated('amount') : null,
            $request->validated('reason'),
        );

        return $this->successResponse(
            (new PaymentResource($refund))->resolve(),
            'Payment refunded successfully',
            201
        );
    }
}
