<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Expenses')]
class ExpenseController extends BaseController
{
    #[OA\Get(path: '/expenses', operationId: 'expensesIndex', summary: 'List expenses (business role)', tags: ['Expenses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated expenses', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $expenses = Expense::query()
            ->with('user:id,name')
            ->when($request->query('category'), fn ($q, string $c) => $q->where('category', $c))
            ->between($request->query('from'), $request->query('to'))
            ->latest('expense_date')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(ExpenseResource::collection($expenses));
    }

    #[OA\Post(path: '/expenses', operationId: 'expensesStore', summary: 'Record an expense', tags: ['Expenses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['category', 'amount', 'expense_date'],
            properties: [
                new OA\Property(property: 'category', type: 'string', example: 'utilities'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Electricity January'),
                new OA\Property(property: 'amount', type: 'number', example: 120.00),
                new OA\Property(property: 'expense_date', type: 'string', format: 'date', example: '2025-01-15'),
                new OA\Property(property: 'receipt', type: 'string', nullable: true),
            ],
        )),
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = Expense::create([
            ...$request->validated(),
            'user_id' => $this->user($request)->id,
        ]);

        return $this->successResponse(
            (new ExpenseResource($expense))->resolve(),
            'Expense recorded successfully',
            201
        );
    }

    #[OA\Get(path: '/expenses/{id}', operationId: 'expensesShow', summary: 'Show one expense', tags: ['Expenses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Expense', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Expense $expense): JsonResponse
    {
        return $this->successResponse((new ExpenseResource($expense->load('user:id,name')))->resolve());
    }

    #[OA\Put(path: '/expenses/{id}', operationId: 'expensesUpdate', summary: 'Update an expense', tags: ['Expenses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense->update($request->validated());

        return $this->successResponse(
            (new ExpenseResource($expense->refresh()))->resolve(),
            'Expense updated successfully'
        );
    }

    #[OA\Delete(path: '/expenses/{id}', operationId: 'expensesDestroy', summary: 'Soft-delete an expense', tags: ['Expenses'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return $this->noContentResponse('Expense deleted successfully');
    }
}
