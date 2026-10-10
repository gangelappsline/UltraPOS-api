<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

/* Polymorphic payments (sale / customer balance / purchase). */

Route::prefix('payments')->controller(PaymentController::class)
    ->middleware(['auth:api', 'role:business,seller', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.payments.index');
        Route::post('/', 'store')->name('api.v1.payments.store');
        Route::get('{payment}', 'show')->name('api.v1.payments.show');
        Route::post('{payment}/refund', 'refund')->name('api.v1.payments.refund');
    });
