<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This project is API-only. The web stack is kept minimal: a landing route
| that points consumers to the Swagger UI, and Passport's authorization
| views are handled by the package itself.
|
*/

// Email verification target for the signed link sent by VerifyEmailMail.
// Returns JSON (API-first) so any client can deep-link the user here.
Route::get('/email/verify/{id}/{hash}', function (\Illuminate\Http\Request $request) {
    $user = \App\Models\User::findOrFail((int) $request->route('id'));

    if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid verification link',
            'error' => ['code' => 'UNAUTHORIZED', 'details' => new stdClass],
            'meta' => ['timestamp' => now()->toIso8601String(), 'request_id' => request_id()],
        ], 403);
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }

    return response()->json([
        'success' => true,
        'message' => 'Email verified successfully',
        'data' => null,
        'meta' => ['timestamp' => now()->toIso8601String(), 'request_id' => request_id()],
    ]);
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::get('/', fn () => response()->json([
    'success' => true,
    'message' => 'UltraPOS API',
    'data' => [
        'documentation' => url('/api/documentation'),
        'openapi_spec' => url('/api/documentation.json'),
        'health' => url('/api/health'),
    ],
    'meta' => [
        'timestamp' => now()->toIso8601String(),
        'request_id' => request()->attributes->get('request_id'),
    ],
]));
