<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CustomerController;
use Illuminate\Support\Facades\Route;

/* Customer CRM, credit accounts and loyalty. */

Route::prefix('customers')->controller(CustomerController::class)
    ->middleware(['auth:api', 'role:business,seller', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.customers.index');
        Route::post('/', 'store')->name('api.v1.customers.store');
        Route::get('{customer}', 'show')->name('api.v1.customers.show');
        Route::put('{customer}', 'update')->name('api.v1.customers.update');
        Route::delete('{customer}', 'destroy')->middleware('role:business')->name('api.v1.customers.destroy');
        Route::get('{customer}/purchases', 'purchases')->name('api.v1.customers.purchases');
        Route::get('{customer}/balance', 'balance')->name('api.v1.customers.balance');
        Route::post('{customer}/payment', 'payment')->name('api.v1.customers.payment');
    });
