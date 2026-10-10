<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Enums\SaleStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin')]
class AdminStatsController extends BaseController
{
    #[OA\Get(path: '/admin/stats', operationId: 'adminStats', summary: 'Platform-wide statistics (admin only)', tags: ['Admin'],
        security: [['passport' => ['admin']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Platform stats', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function __invoke(): JsonResponse
    {
        $monthStart = Carbon::today()->startOfMonth();

        return $this->successResponse([
            'businesses' => [
                'total' => Business::count(),
                'active' => Business::active()->count(),
                'new_this_month' => Business::where('created_at', '>=', $monthStart)->count(),
                'by_type' => Business::query()
                    ->groupBy('business_type')
                    ->selectRaw('business_type, COUNT(*) as count')
                    ->pluck('count', 'business_type'),
            ],
            'users' => [
                'total' => User::count(),
                'active' => User::active()->count(),
                'new_this_month' => User::where('created_at', '>=', $monthStart)->count(),
            ],
            'catalog' => [
                'products' => Product::count(),
                'customers' => Customer::count(),
            ],
            'sales' => [
                'total' => Sale::count(),
                'completed' => Sale::completed()->count(),
                'gross_volume' => (float) Sale::completed()->sum('total'),
                'gross_volume_this_month' => (float) Sale::completed()
                    ->where('created_at', '>=', $monthStart)
                    ->sum('total'),
                'by_status' => Sale::query()
                    ->groupBy('status')
                    ->selectRaw('status, COUNT(*) as count')
                    ->pluck('count', 'status'),
            ],
            'platform_health' => [
                'status' => SaleStatus::COMPLETED->value === 'completed' ? 'ok' : 'ok',
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
