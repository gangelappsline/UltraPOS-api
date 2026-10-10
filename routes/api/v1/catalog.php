<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\UnitController;
use Illuminate\Support\Facades\Route;

/*
| Catalog module.
| Reads: business + seller. Writes: business owners only.
*/

// ── Categories ─────────────────────────────────────────────────────────────
Route::prefix('categories')->controller(CategoryController::class)
    ->middleware(['auth:api', 'role:business,seller', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.categories.index');
        Route::get('tree', 'tree')->name('api.v1.categories.tree');
        Route::post('/', 'store')->middleware('role:business')->name('api.v1.categories.store');
        Route::get('{category}', 'show')->name('api.v1.categories.show');
        Route::put('{category}', 'update')->middleware('role:business')->name('api.v1.categories.update');
        Route::delete('{category}', 'destroy')->middleware('role:business')->name('api.v1.categories.destroy');
    });

// ── Brands ─────────────────────────────────────────────────────────────────
Route::prefix('brands')->controller(BrandController::class)
    ->middleware(['auth:api', 'role:business,seller', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.brands.index');
        Route::post('/', 'store')->middleware('role:business')->name('api.v1.brands.store');
        Route::get('{brand}', 'show')->name('api.v1.brands.show');
        Route::put('{brand}', 'update')->middleware('role:business')->name('api.v1.brands.update');
        Route::delete('{brand}', 'destroy')->middleware('role:business')->name('api.v1.brands.destroy');
    });

// ── Units of measure ───────────────────────────────────────────────────────
Route::prefix('units')->controller(UnitController::class)
    ->middleware(['auth:api', 'role:business,seller', 'business.scope'])
    ->group(function (): void {
        Route::get('/', 'index')->name('api.v1.units.index');
        Route::post('/', 'store')->middleware('role:business')->name('api.v1.units.store');
        Route::get('{unit}', 'show')->name('api.v1.units.show');
        Route::put('{unit}', 'update')->middleware('role:business')->name('api.v1.units.update');
        Route::delete('{unit}', 'destroy')->middleware('role:business')->name('api.v1.units.destroy');
    });

// ── Products ───────────────────────────────────────────────────────────────
Route::prefix('products')->middleware(['auth:api', 'role:business,seller', 'business.scope'])->group(function (): void {
    Route::controller(ProductController::class)->group(function (): void {
        Route::get('/', 'index')->name('api.v1.products.index');
        Route::post('/', 'store')->middleware('role:business')->name('api.v1.products.store');

        // Static segments BEFORE the {product} binding.
        Route::get('barcode/{barcode}', 'barcode')->name('api.v1.products.barcode');
        Route::get('low-stock', 'lowStock')->name('api.v1.products.low-stock');
        Route::get('export', 'export')->name('api.v1.products.export');
        Route::post('import', 'import')->middleware('role:business')->name('api.v1.products.import');

        Route::get('{product}', 'show')->name('api.v1.products.show');
        Route::put('{product}', 'update')->middleware('role:business')->name('api.v1.products.update');
        Route::delete('{product}', 'destroy')->middleware('role:business')->name('api.v1.products.destroy');
        Route::post('{product}/adjust-stock', 'adjustStock')->name('api.v1.products.adjust-stock');
    });

    // Nested variants.
    Route::controller(ProductVariantController::class)->prefix('products/{product}/variants')->group(function (): void {
        Route::get('/', 'index')->name('api.v1.products.variants.index');
        Route::post('/', 'store')->middleware('role:business')->name('api.v1.products.variants.store');
        Route::get('{variant}', 'show')->name('api.v1.products.variants.show');
        Route::put('{variant}', 'update')->middleware('role:business')->name('api.v1.products.variants.update');
        Route::delete('{variant}', 'destroy')->middleware('role:business')->name('api.v1.products.variants.destroy');
    });
});
