<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    'passphrase' => env('PASSPORT_PASSPHRASE'),

    /*
    |--------------------------------------------------------------------------
    | Client UUIDs
    |--------------------------------------------------------------------------
    */

    'client_uuids' => false,

    /*
    |--------------------------------------------------------------------------
    | Personal Access Client
    |--------------------------------------------------------------------------
    */

    'personal_access_client' => [
        'id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
        'secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Grant Client (used by first-party POS apps)
    |--------------------------------------------------------------------------
    */

    'password_grant_client' => [
        'id' => env('PASSPORT_PASSWORD_GRANT_CLIENT_ID'),
        'secret' => env('PASSPORT_PASSWORD_GRANT_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes
    |--------------------------------------------------------------------------
    */

    'token_lifetimes' => [
        // Default access token lifetime (minutes) issued by UltraPOS auth
        // endpoints. The OAuth2 password grant uses its own client config.
        'access' => (int) env('PASSPORT_ACCESS_TOKEN_TTL', 1440),
        'refresh' => (int) env('PASSPORT_REFRESH_TOKEN_TTL', 43200),
        'personal_access' => (int) env('PASSPORT_PERSONAL_ACCESS_TOKEN_TTL', 10080),
    ],

];
