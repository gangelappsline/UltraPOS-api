<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
| Auth module. Public endpoints are rate limited; protected ones require a
| Passport token (auth:api).
*/

Route::prefix('auth')->controller(AuthController::class)->group(function (): void {
    // ── Public ────────────────────────────────────────────────────────
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('register', 'register')->name('api.v1.auth.register');
        Route::post('forgot-password', 'forgotPassword')->name('api.v1.auth.forgot-password');
        Route::post('reset-password', 'resetPassword')->name('api.v1.auth.reset-password');
    });

    Route::post('login', 'login')
        ->middleware('throttle:5,1')
        ->name('api.v1.auth.login');

    // ── Protected ─────────────────────────────────────────────────────
    Route::middleware('auth:api')->group(function (): void {
        Route::post('refresh', 'refresh')->middleware('throttle:30,1')->name('api.v1.auth.refresh');
        Route::post('logout', 'logout')->name('api.v1.auth.logout');
        Route::get('me', 'me')->name('api.v1.auth.me');
        Route::put('profile', 'updateProfile')->name('api.v1.auth.profile.update');
        Route::put('password', 'updatePassword')->middleware('throttle:5,1')->name('api.v1.auth.password.update');
        Route::post('verify-email', 'verifyEmail')->middleware('throttle:5,1')->name('api.v1.auth.verify-email');
        Route::post('resend-verification', 'resendVerification')->middleware('throttle:3,1')->name('api.v1.auth.resend-verification');
    });
});
