<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Reports')]
class ReportController extends BaseController
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    #[OA\Get(path: '/reports/sales', operationId: 'reportsSales', summary: 'Sales report with totals + time series', tags: ['Reports'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'group_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['day', 'month'], default: 'day')),
            new OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sale_type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pos', 'online', 'phone'])),
            new OA\Parameter(name: 'payment_status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['unpaid', 'partial', 'paid'])),
        ],
        responses: [new OA\Response(response: 200, description: 'Sales report', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function sales(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->sales(
            $this->businessId($request),
            $this->date($request, 'from'),
            $this->date($request, 'to'),
            (string) $request->query('group_by', 'day'),
            $request->filled('user_id') ? (int) $request->query('user_id') : null,
            $request->query('sale_type'),
            $request->query('payment_status'),
        ));
    }

    #[OA\Get(path: '/reports/products/top-selling', operationId: 'reportsTopSelling', summary: 'Top selling products by revenue', tags: ['Reports'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [new OA\Response(response: 200, description: 'Top products', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function topSelling(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->topSellingProducts(
            $this->businessId($request),
            $this->date($request, 'from'),
            $this->date($request, 'to'),
            min(100, max(1, (int) $request->query('limit', 10))),
        ));
    }

    #[OA\Get(path: '/reports/products/low-stock', operationId: 'reportsLowStock', summary: 'Low stock report with reorder suggestion', tags: ['Reports'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Low stock report', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function lowStock(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->lowStockProducts($this->businessId($request)));
    }

    #[OA\Get(path: '/reports/customers/top', operationId: 'reportsTopCustomers', summary: 'Top customers by spend', tags: ['Reports'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [new OA\Response(response: 200, description: 'Top customers', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function topCustomers(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->topCustomers(
            $this->businessId($request),
            $this->date($request, 'from'),
            $this->date($request, 'to'),
            min(100, max(1, (int) $request->query('limit', 10))),
        ));
    }

    #[OA\Get(path: '/reports/profit', operationId: 'reportsProfit', summary: 'Profit report (revenue − COGS − expenses)', tags: ['Reports'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Profit report', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function profit(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->profit(
            $this->businessId($request),
            $this->date($request, 'from'),
            $this->date($request, 'to'),
        ));
    }

    #[OA\Get(path: '/reports/inventory/valuation', operationId: 'reportsInventoryValuation', summary: 'Inventory valuation at cost & retail', tags: ['Reports'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Valuation', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function inventoryValuation(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->inventoryValuation($this->businessId($request)));
    }

    #[OA\Get(path: '/reports/tax', operationId: 'reportsTax', summary: 'Tax collected report grouped by rate', tags: ['Reports'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OA\Response(response: 200, description: 'Tax report', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function tax(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->tax(
            $this->businessId($request),
            $this->date($request, 'from'),
            $this->date($request, 'to'),
        ));
    }

    #[OA\Get(path: '/reports/dashboard', operationId: 'reportsDashboard', summary: 'Full dashboard payload (stats + alerts + recents)', tags: ['Reports'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Dashboard', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function dashboard(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->dashboard($this->businessId($request)));
    }

    private function date(Request $request, string $key): ?Carbon
    {
        return $request->filled($key) ? Carbon::parse((string) $request->query($key)) : null;
    }
}
