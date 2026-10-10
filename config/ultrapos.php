<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UltraPOS Business Rules
|--------------------------------------------------------------------------
| Runtime tenant values (currency) are overwritten by the SetBusinessLocale
| middleware; the values here are safe defaults.
*/

return [

    // Currency of the current tenant (USD fallback).
    'currency' => env('ULTRAPOS_CURRENCY', 'USD'),

    // Loyalty points awarded per currency unit spent on a completed sale.
    'loyalty_points_per_unit' => (int) env('POS_LOYALTY_POINTS_PER_UNIT', 1),

    // Emergency mode: allow selling below zero stock. Keep false in prod.
    'allow_negative_stock' => (bool) env('POS_ALLOW_NEGATIVE_STOCK', false),

    // Reference number prefixes per document type.
    'references' => [
        'sale' => 'SAL',
        'purchase' => 'PUR',
    ],

    // Default subscription trial (days) granted on business registration.
    'trial_days' => (int) env('ULTRAPOS_TRIAL_DAYS', 14),

    // Days before ends_at to warn about an expiring subscription.
    'subscription_warning_days' => (int) env('ULTRAPOS_SUBSCRIPTION_WARNING_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Development safeguards
    |--------------------------------------------------------------------------
    */

    // Turn Eloquent into a strict mode: lazy loading, non-fillable mass
    // assignment and missing attributes throw instead of silently working.
    // Recommended while developing; keep false so a fresh install (migrate
    // --seed, the demo tenant, the POS happy path) works out of the box.
    'strict_models' => (bool) env('ULTRAPOS_STRICT_MODELS', false),

    // Expose the OAuth2 "password" grant on POST /oauth/token (Passport 13
    // keeps it disabled by default). The first-party /api/v1/auth/login
    // endpoint works regardless of this flag.
    'password_grant_enabled' => (bool) env('ULTRAPOS_PASSWORD_GRANT', true),
];
