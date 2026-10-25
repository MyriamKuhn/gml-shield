<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Reject authenticated requests from disabled accounts.
     *
     * This check applies to existing sessions as well as
     * newly authenticated requests.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if (!$user->is_active) {
            abort(403, 'This account is disabled.');
        }

        return $next($request);
    }
}
