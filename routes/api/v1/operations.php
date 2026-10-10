<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\ExpenseController;
use Illuminate\Support\Facades\Route;

/* Cash registers (business + seller) and expenses (business only). */

Route::prefix('cash-registers')->controller(CashRegisterController::class)
    ->middleware(['auth:api', 'role:business,seller', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.cash-registers.index');
        Route::post('open', 'open')->name('api.v1.cash-registers.open');
        Route::get('{cashRegister}', 'show')->name('api.v1.cash-registers.show');
        Route::post('{cashRegister}/close', 'close')->name('api.v1.cash-registers.close');
        Route::get('{cashRegister}/movements', 'movements')->name('api.v1.cash-registers.movements');
        Route::post('{cashRegister}/movements', 'storeMovement')->name('api.v1.cash-registers.movements.store');
    });

Route::prefix('expenses')->controller(ExpenseController::class)
    ->middleware(['auth:api', 'role:business', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.expenses.index');
        Route::post('/', 'store')->name('api.v1.expenses.store');
        Route::get('{expense}', 'show')->name('api.v1.expenses.show');
        Route::put('{expense}', 'update')->name('api.v1.expenses.update');
        Route::delete('{expense}', 'destroy')->name('api.v1.expenses.destroy');
    });
