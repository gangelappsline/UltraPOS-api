<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

/* In-app notification center (every authenticated role). */

Route::prefix('notifications')->controller(NotificationController::class)
    ->middleware('auth:api')
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.notifications.index');
        Route::post('read-all', 'markAllRead')->name('api.v1.notifications.read-all');
        Route::post('{id}/read', 'markRead')->name('api.v1.notifications.read');
    });
