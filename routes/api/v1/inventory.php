<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\InventoryController;
use Illuminate\Support\Facades\Route;

/* Stock ledger + inventory report. */

Route::prefix('inventory')->controller(InventoryController::class)
    ->middleware(['auth:api', 'role:business,seller', 'business.scope'])
    ->group(function (): void {
        Route::get('movements', 'movements')->name('api.v1.inventory.movements');
        Route::post('movements', 'storeMovement')->name('api.v1.inventory.movements.store');
        Route::get('report', 'report')->middleware('role:business')->name('api.v1.inventory.report');
    });
