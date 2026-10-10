<?php

declare(strict_types=1);

use App\Exceptions\Handler as ExceptionHandler;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureBusinessScope;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetBusinessLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/api/health/ping',
    )
    // Register the Artisan commands in app/Console/Commands. Laravel only
    // auto-discovers that folder through withCommands(); passing `commands`
    // to withRouting() loads just the Closure-based routes file, so every
    // `ultrapos:*` command was reported as "command not found" and
    // `php artisan schedule:run` silently failed.
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
        __DIR__.'/../routes/console.php',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // API-wide middleware: force JSON negotiation + request tracing id.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            AssignRequestId::class,
        ]);

        // Per-business timezone/locale/currency context (runs after auth).
        $middleware->api(append: [
            SetBusinessLocale::class,
        ]);

        // Route middleware aliases.
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'business.scope' => EnsureBusinessScope::class,
        ]);

        // Stateless API: never redirect to a login route.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Global API exception formatter (standardized envelope + error codes).
        ExceptionHandler::register($exceptions);
    })->create();
