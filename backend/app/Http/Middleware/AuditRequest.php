<?php

namespace App\Http\Middleware;

use App\Jobs\RecordAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditRequest
{
    /**
     * Audit modifying HTTP requests without recording sensitive payloads.
     *
     * The response status distinguishes successful operations
     * from rejected attempts.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            !$request->isMethod('POST')
            && !$request->isMethod('PATCH')
            && !$request->isMethod('PUT')
            && !$request->isMethod('DELETE')
        ) {
            return $response;
        }

        // Keep the audit action independent of user-controlled input.
        $route = $request->route();

        $routeName = $route?->getName()
            ?? $route?->uri()
            ?? 'unknown';

        $action = sprintf(
            '%s %s [%d]',
            $request->method(),
            $routeName,
            $response->getStatusCode()
        );

        try {
            RecordAuditLog::dispatch(
                userId: $request->user()?->id,
                action: $action,
                ipAddress: $request->ip(),
                occurredAt: now()->toDateTimeString(),
            );
        } catch (Throwable $exception) {
            // Report audit infrastructure failures without exposing
            // request contents, credentials or business secrets.
            Log::error('Audit dispatch failed.', [
                'exception_type' => get_class($exception),
            ]);
        }

        return $response;
    }
}
