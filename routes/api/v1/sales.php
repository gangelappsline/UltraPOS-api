<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\SaleController;
use Illuminate\Support\Facades\Route;

/*
| POS sales lifecycle.
| Create/read: business + seller. Cancel/refund: business owners only.
| NOTE: static segments (daily-summary) are registered before {sale}.
*/

Route::prefix('sales')->controller(SaleController::class)
    ->middleware(['auth:api', 'role:business,seller', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.sales.index');
        Route::post('/', 'store')->middleware('throttle:60,1')->name('api.v1.sales.store');
        Route::get('daily-summary', 'dailySummary')->middleware('role:business')->name('api.v1.sales.daily-summary');

        Route::get('{sale}', 'show')->name('api.v1.sales.show');
        Route::get('{sale}/receipt', 'receipt')->name('api.v1.sales.receipt');
        Route::post('{sale}/send-receipt', 'sendReceipt')->name('api.v1.sales.send-receipt');
        Route::post('{sale}/cancel', 'cancel')->middleware('role:business')->name('api.v1.sales.cancel');
        Route::post('{sale}/refund', 'refund')->middleware('role:business')->name('api.v1.sales.refund');
    });
