<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Dashboard')]
class DashboardController extends BaseController
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    #[OA\Get(path: '/dashboard/stats', operationId: 'dashboardStats', summary: 'KPI stats for the POS dashboard', tags: ['Dashboard'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Stats', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function stats(Request $request): JsonResponse
    {
        $dashboard = $this->reports->dashboard($this->businessId($request));

        return $this->successResponse([
            'today' => $dashboard['today'],
            'month' => $dashboard['month'],
            'counters' => $dashboard['counters'],
            'top_products_today' => $dashboard['top_products_today'],
        ]);
    }

    #[OA\Get(path: '/dashboard/recent-sales', operationId: 'dashboardRecentSales', summary: 'Last sales of the tenant', tags: ['Dashboard'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10))],
        responses: [new OA\Response(response: 200, description: 'Recent sales', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function recentSales(Request $request): JsonResponse
    {
        $sales = Sale::query()
            ->with(['customer:id,name', 'user:id,name'])
            ->latest('id')
            ->limit(min(50, max(1, (int) $request->query('limit', 10))))
            ->get();

        return $this->successResponse(SaleResource::collection($sales)->resolve());
    }

    #[OA\Get(path: '/dashboard/alerts', operationId: 'dashboardAlerts', summary: 'Operational alerts (low stock, expiring products, subscription...)', tags: ['Dashboard'],
        security: [['passport' => ['business', 'seller']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Alerts', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function alerts(Request $request): JsonResponse
    {
        return $this->successResponse($this->reports->alerts($this->businessId($request)));
    }
}
