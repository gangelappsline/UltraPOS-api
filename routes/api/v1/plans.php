<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PlanController;
use Illuminate\Support\Facades\Route;

/* Public plans catalog (marketing/pricing screens). */

Route::prefix('plans')->controller(PlanController::class)->middleware('throttle:30,1')->group(function (): void {
    Route::get('/', 'index')->name('api.v1.plans.index');
    Route::get('{plan}', 'show')->name('api.v1.plans.show');
});
