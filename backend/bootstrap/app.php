<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\AuditRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        [
            'prefix' => 'api',
            'middleware' => ['api', 'auth:sanctum', 'active'],
        ],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy only when explicitly configured.
        $trustedProxies = env('TRUSTED_PROXIES');

        if ($trustedProxies) {
            $middleware->trustProxies(
                at: array_map('trim', explode(',', $trustedProxies))
            );
        }

        // API-only authentication: do not redirect guests to a missing login page.
        $middleware->redirectGuestsTo(null);

        // Enable Sanctum's session-based authentication for our React SPA.
        $middleware->statefulApi();

        // Audit API requests before authentication and authorization
        // so rejected attempts can also be recorded.
        $middleware->api(prepend: [
            AuditRequest::class,
        ]);

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
