<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BusinessController;
use Illuminate\Support\Facades\Route;

/* Tenants (businesses). Owners manage their own; admins manage all. */

Route::prefix('businesses')->controller(BusinessController::class)
    ->middleware(['auth:api', 'role:business,admin'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.businesses.index');
        Route::post('/', 'store')->middleware('role:admin')->name('api.v1.businesses.store');

        Route::prefix('{business}')->middleware('business.scope')->group(function (): void {
            Route::get('/', 'show')->name('api.v1.businesses.show');
            Route::put('/', 'update')->name('api.v1.businesses.update');
            Route::delete('/', 'destroy')->name('api.v1.businesses.destroy');
            Route::get('settings', 'settings')->name('api.v1.businesses.settings');
            Route::put('settings', 'updateSettings')->name('api.v1.businesses.settings.update');
        });
    });
