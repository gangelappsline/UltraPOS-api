<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All endpoints live under /api (prefix applied by withRouting in
| bootstrap/app.php). Versioned modules are loaded from routes/api/v1/*.
|
| Middleware stack applied to this group (bootstrap/app.php):
|   ForceJsonResponse → AssignRequestId → [route middleware] → SetBusinessLocale
|
*/

// ── Health check (public, no auth) ────────────────────────────────────────
Route::get('/health', HealthController::class)->name('api.health');

// ── Version 1 ─────────────────────────────────────────────────────────────
Route::prefix('v1')->group(function (): void {
    $modules = [
        'auth',          // register/login/token lifecycle (public + protected)
        'plans',         // public SaaS plans catalog
        'system',        // public metadata (business types)
        'business',      // tenants + tenant settings
        'users',         // staff management
        'catalog',       // categories, brands, units, products, variants
        'inventory',     // stock ledger & reports
        'customers',     // CRM, credit & loyalty
        'purchasing',    // suppliers + purchase orders
        'sales',         // POS sales lifecycle
        'payments',      // polymorphic payments
        'operations',    // cash registers + expenses
        'reports',       // analytics & dashboard
        'notifications', // in-app notification center
        'subscriptions', // tenant subscription lifecycle
        'admin',         // global administration
    ];

    foreach ($modules as $module) {
        require __DIR__."/api/v1/{$module}.php";
    }
});
