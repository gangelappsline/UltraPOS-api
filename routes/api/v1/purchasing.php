<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Support\Facades\Route;

/* Suppliers + purchase orders (business owners only). */

Route::middleware(['auth:api', 'role:business', 'business.scope'])->group(function (): void {
    Route::prefix('suppliers')->controller(SupplierController::class)->group(function (): void {
        Route::get('/', 'index')->name('api.v1.suppliers.index');
        Route::post('/', 'store')->name('api.v1.suppliers.store');
        Route::get('{supplier}', 'show')->name('api.v1.suppliers.show');
        Route::put('{supplier}', 'update')->name('api.v1.suppliers.update');
        Route::delete('{supplier}', 'destroy')->name('api.v1.suppliers.destroy');
    });

    Route::prefix('purchases')->controller(PurchaseController::class)->group(function (): void {
        Route::get('/', 'index')->name('api.v1.purchases.index');
        Route::post('/', 'store')->name('api.v1.purchases.store');
        Route::get('{purchase}', 'show')->name('api.v1.purchases.show');
        Route::put('{purchase}', 'update')->name('api.v1.purchases.update');
        Route::delete('{purchase}', 'destroy')->name('api.v1.purchases.destroy');
        Route::post('{purchase}/receive', 'receive')->name('api.v1.purchases.receive');
        Route::post('{purchase}/cancel', 'cancel')->name('api.v1.purchases.cancel');
    });
});
