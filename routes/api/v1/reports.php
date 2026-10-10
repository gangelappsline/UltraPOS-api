<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

/* Analytics (owners) + POS dashboard widgets (owners & sellers). */

Route::middleware(['auth:api', 'business.scope'])->group(function (): void {
    Route::prefix('reports')->controller(ReportController::class)
        ->middleware('role:business')
        ->group(function (): void {
            Route::get('sales', 'sales')->name('api.v1.reports.sales');
            Route::get('products/top-selling', 'topSelling')->name('api.v1.reports.products.top-selling');
            Route::get('products/low-stock', 'lowStock')->name('api.v1.reports.products.low-stock');
            Route::get('customers/top', 'topCustomers')->name('api.v1.reports.customers.top');
            Route::get('profit', 'profit')->name('api.v1.reports.profit');
            Route::get('inventory/valuation', 'inventoryValuation')->name('api.v1.reports.inventory.valuation');
            Route::get('tax', 'tax')->name('api.v1.reports.tax');
            Route::get('dashboard', 'dashboard')->name('api.v1.reports.dashboard');
        });

    Route::prefix('dashboard')->controller(DashboardController::class)
        ->middleware('role:business,seller')
        ->group(function (): void {
            Route::get('stats', 'stats')->name('api.v1.dashboard.stats');
            Route::get('recent-sales', 'recentSales')->name('api.v1.dashboard.recent-sales');
            Route::get('alerts', 'alerts')->name('api.v1.dashboard.alerts');
        });
});
