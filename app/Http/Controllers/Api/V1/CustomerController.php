<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Customers\StoreCustomerPaymentRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Customers')]
class CustomerController extends BaseController
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    #[OA\Get(path: '/customers', operationId: 'customersIndex', summary: 'List customers (paginated)', tags: ['Customers'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'with_balance', in: 'query', description: 'Only customers owing money', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated customers', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->search($request->query('search'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('with_balance'), fn ($q) => $q->where('current_balance', '>', 0))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(CustomerResource::collection($customers));
    }

    #[OA\Post(path: '/customers', operationId: 'customersStore', summary: 'Create a customer', tags: ['Customers'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'John Smith'),
                new OA\Property(property: 'email', type: 'string', nullable: true, example: 'john@mail.test'),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'tax_id', type: 'string', nullable: true),
                new OA\Property(property: 'credit_limit', type: 'number', example: 5000),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create($request->validated());

        return $this->successResponse(
            (new CustomerResource($customer))->resolve(),
            'Customer created successfully',
            201
        );
    }

    #[OA\Get(path: '/customers/{id}', operationId: 'customersShow', summary: 'Show one customer', tags: ['Customers'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Customer', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->successResponse(
            (new CustomerResource($customer->loadCount('sales')))->resolve()
        );
    }

    #[OA\Put(path: '/customers/{id}', operationId: 'customersUpdate', summary: 'Update a customer', tags: ['Customers'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $payload = $request->validated();

        // Only owners may change credit limits / loyalty points.
        if (! $this->user($request)->isBusinessOwner() && ! $this->user($request)->isAdmin()) {
            unset($payload['credit_limit'], $payload['loyalty_points']);
        }

        $customer->update($payload);

        return $this->successResponse(
            (new CustomerResource($customer->refresh()))->resolve(),
            'Customer updated successfully'
        );
    }

    #[OA\Delete(path: '/customers/{id}', operationId: 'customersDestroy', summary: 'Soft-delete a customer (owner)', tags: ['Customers'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return $this->noContentResponse('Customer deleted successfully');
    }

    #[OA\Get(path: '/customers/{id}/purchases', operationId: 'customersPurchases', summary: 'Purchase history of a customer', tags: ['Customers'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated sales', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function purchases(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $sales = Sale::query()
            ->where('customer_id', $customer->id)
            ->with('items:id,sale_id,product_name,quantity,total')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return $this->paginatedResponse(SaleResource::collection($sales));
    }

    #[OA\Get(path: '/customers/{id}/balance', operationId: 'customersBalance', summary: 'Credit account status', tags: ['Customers'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Balance summary', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function balance(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $unpaidSales = Sale::query()
            ->where('customer_id', $customer->id)
            ->completed()
            ->where('payment_status', '!=', 'paid')
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(total - paid_amount), 0) as s')
            ->first();

        return $this->successResponse([
            'credit_limit' => (float) $customer->credit_limit,
            'current_balance' => (float) $customer->current_balance,
            'available_credit' => $customer->availableCredit(),
            'loyalty_points' => (int) $customer->loyalty_points,
            'unpaid_sales_count' => (int) ($unpaidSales->c ?? 0),
            'unpaid_sales_amount' => (float) ($unpaidSales->s ?? 0),
            'recent_payments' => PaymentResource::collection(
                Payment::query()->where('payable_type', Customer::class)
                    ->where('payable_id', $customer->id)
                    ->latest('id')->limit(5)->get()
            )->resolve(),
        ]);
    }

    #[OA\Post(path: '/customers/{id}/payment', operationId: 'customersPayment', summary: 'Register a payment against the customer balance', tags: ['Customers'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['amount', 'method'],
            properties: [
                new OA\Property(property: 'amount', type: 'number', example: 150.00),
                new OA\Property(property: 'method', type: 'string', enum: ['cash', 'card', 'transfer', 'credit', 'mixed'], example: 'cash'),
                new OA\Property(property: 'reference', type: 'string', nullable: true),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Payment registered', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Amount exceeds balance', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function payment(StoreCustomerPaymentRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('recordPayment', $customer);

        $payment = $this->payments->createForDocument(
            $this->user($request),
            Customer::class,
            $customer->id,
            $request->validated(),
        );

        return $this->successResponse(
            [
                'payment' => (new PaymentResource($payment))->resolve(),
                'customer' => (new CustomerResource($customer->refresh()))->resolve(),
            ],
            'Customer payment registered successfully',
            201
        );
    }
}
