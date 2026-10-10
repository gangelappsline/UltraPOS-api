<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminBusinessController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminStatsController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

/* Global administration — role:admin only. */

Route::prefix('admin')->middleware(['auth:api', 'role:admin'])->group(function (): void {
    Route::controller(AdminBusinessController::class)->prefix('businesses')->group(function (): void {
        Route::get('/', 'index')->name('api.v1.admin.businesses.index');
        Route::put('{business}/toggle-status', 'toggleStatus')->name('api.v1.admin.businesses.toggle-status');
    });

    Route::get('users', [AdminUserController::class, 'index'])->name('api.v1.admin.users.index');

    Route::controller(AdminPlanController::class)->prefix('plans')->group(function (): void {
        Route::get('/', 'index')->name('api.v1.admin.plans.index');
        Route::post('/', 'store')->name('api.v1.admin.plans.store');
        Route::put('{plan}', 'update')->name('api.v1.admin.plans.update');
        Route::delete('{plan}', 'destroy')->name('api.v1.admin.plans.destroy');
    });

    Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('api.v1.admin.audit-logs.index');
    Route::get('stats', AdminStatsController::class)->name('api.v1.admin.stats');
});
