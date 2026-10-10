<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CashMovementType;
use App\Http\Controllers\BaseController;
use App\Http\Requests\CashRegisters\CloseCashRegisterRequest;
use App\Http\Requests\CashRegisters\OpenCashRegisterRequest;
use App\Http\Requests\CashRegisters\StoreCashMovementRequest;
use App\Http\Resources\CashMovementResource;
use App\Http\Resources\CashRegisterResource;
use App\Models\CashRegister;
use App\Services\CashRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'CashRegisters')]
class CashRegisterController extends BaseController
{
    public function __construct(private readonly CashRegisterService $registers)
    {
    }

    #[OA\Get(path: '/cash-registers', operationId: 'cashRegistersIndex', summary: 'List cash registers (owners: tenant-wide, sellers: own)', tags: ['CashRegisters'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['open', 'closed'])),
            new OA\Parameter(name: 'mine', in: 'query', description: 'Only my registers', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated registers', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $registers = CashRegister::query()
            ->with('user:id,name')
            ->when(! $user->isBusinessOwner() && ! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->boolean('mine'), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->query('status'), fn ($q, string $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return $this->paginatedResponse(CashRegisterResource::collection($registers));
    }

    #[OA\Post(path: '/cash-registers/open', operationId: 'cashRegistersOpen', summary: 'Open a cash register (one open session per user)', tags: ['CashRegisters'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['opening_amount'],
            properties: [
                new OA\Property(property: 'opening_amount', type: 'number', example: 500.00),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Register opened', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'You already have an open register', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function open(OpenCashRegisterRequest $request): JsonResponse
    {
        $register = $this->registers->open(
            $this->user($request),
            (float) $request->validated('opening_amount'),
            $request->validated('notes'),
        );

        return $this->successResponse(
            (new CashRegisterResource($register->loadCount('movements')))->resolve(),
            'Cash register opened successfully',
            201
        );
    }

    #[OA\Get(path: '/cash-registers/{id}', operationId: 'cashRegistersShow', summary: 'Show one register session', tags: ['CashRegisters'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Register', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function show(Request $request, CashRegister $cashRegister): JsonResponse
    {
        $this->guardAccess($request, $cashRegister);

        return $this->successResponse(
            (new CashRegisterResource($cashRegister->load(['user:id,name'])))->resolve()
        );
    }

    #[OA\Post(path: '/cash-registers/{id}/close', operationId: 'cashRegistersClose', summary: 'Close a register (computes expected amount & difference)', tags: ['CashRegisters'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['closing_amount'],
            properties: [
                new OA\Property(property: 'closing_amount', type: 'number', example: 1284.50),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Register closed', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Already closed / not yours', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function close(CloseCashRegisterRequest $request, CashRegister $cashRegister): JsonResponse
    {
        $cashRegister = $this->registers->close(
            $cashRegister,
            $this->user($request),
            (float) $request->validated('closing_amount'),
            $request->validated('notes'),
        );

        return $this->successResponse(
            (new CashRegisterResource($cashRegister))->resolve(),
            'Cash register closed successfully'
        );
    }

    #[OA\Get(path: '/cash-registers/{id}/movements', operationId: 'cashRegistersMovements', summary: 'Movements of a register session', tags: ['CashRegisters'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Movements', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function movements(Request $request, CashRegister $cashRegister): JsonResponse
    {
        $this->guardAccess($request, $cashRegister);

        $movements = $cashRegister->movements()
            ->with('user:id,name')
            ->orderBy('id')
            ->get();

        return $this->successResponse([
            'register' => (new CashRegisterResource($cashRegister))->resolve(),
            'movements' => CashMovementResource::collection($movements)->resolve(),
            'balance' => $cashRegister->movementsBalance(),
        ]);
    }

    #[OA\Post(path: '/cash-registers/{id}/movements', operationId: 'cashRegistersStoreMovement', summary: 'Cash-in (deposit) / cash-out (withdrawal)', tags: ['CashRegisters'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['type', 'amount'],
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['withdrawal', 'deposit'], example: 'withdrawal'),
                new OA\Property(property: 'amount', type: 'number', example: 200.00),
                new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Supplier payment'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Movement recorded', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Exceeds drawer balance / closed register', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function storeMovement(StoreCashMovementRequest $request, CashRegister $cashRegister): JsonResponse
    {
        $user = $this->user($request);
        $this->guardAccess($request, $cashRegister);

        $movement = $this->registers->addMovement(
            $cashRegister,
            $user,
            CashMovementType::from((string) $request->validated('type')),
            (float) $request->validated('amount'),
            $request->validated('notes'),
        );

        return $this->successResponse(
            (new CashMovementResource($movement))->resolve(),
            'Cash movement recorded successfully',
            201
        );
    }

    /** Sellers only touch their own register; owners/admins touch any. */
    private function guardAccess(Request $request, CashRegister $register): void
    {
        $user = $this->user($request);

        if ($register->user_id !== $user->id && ! $user->isBusinessOwner() && ! $user->isAdmin()) {
            abort(403, 'You can only access your own cash register.');
        }
    }
}
