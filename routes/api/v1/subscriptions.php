<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

/* Tenant subscription lifecycle (business owners). */

Route::prefix('subscriptions')->controller(SubscriptionController::class)
    ->middleware(['auth:api', 'role:business', 'business.scope'])
    ->group(function (): void {
        Route::get('current', 'current')->name('api.v1.subscriptions.current');
        Route::get('history', 'history')->name('api.v1.subscriptions.history');
        Route::post('upgrade', 'upgrade')->name('api.v1.subscriptions.upgrade');
        Route::post('cancel', 'cancel')->name('api.v1.subscriptions.cancel');
    });
