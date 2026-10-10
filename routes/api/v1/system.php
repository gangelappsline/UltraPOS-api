<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BusinessTypeController;
use Illuminate\Support\Facades\Route;

/* Public metadata endpoints. */

Route::get('business-types', BusinessTypeController::class)
    ->middleware('throttle:30,1')
    ->name('api.v1.business-types');
