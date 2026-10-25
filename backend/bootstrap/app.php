<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use App\Http\Middleware\EnsureUserIsActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only authentication: do not redirect guests to a missing login page.
        $middleware->redirectGuestsTo(null);

        // Enable Sanctum's session-based authentication for our React SPA.
        $middleware->statefulApi();

        // Register role-based authorization middleware.
        // 'role' checks for user roles.
        // 'active' ensures the user account is active.
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*')
                || $request->is('docs/api')
                || $request->is('docs/api.json')
                || $request->expectsJson(),
        );
    })->create();
