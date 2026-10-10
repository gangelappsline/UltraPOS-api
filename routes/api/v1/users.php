<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/* Staff management inside the active tenant (business owners + admins). */

Route::prefix('users')->controller(UserController::class)
    ->middleware(['auth:api', 'role:business,admin', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.users.index');
        Route::post('/', 'store')->name('api.v1.users.store');
        Route::get('{user}', 'show')->name('api.v1.users.show');
        Route::put('{user}', 'update')->name('api.v1.users.update');
        Route::delete('{user}', 'destroy')->name('api.v1.users.destroy');
        Route::post('{user}/assign-role', 'assignRole')->name('api.v1.users.assign-role');
        Route::post('{user}/toggle-status', 'toggleStatus')->name('api.v1.users.toggle-status');
    });
