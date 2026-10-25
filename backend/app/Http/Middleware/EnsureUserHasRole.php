<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Ensure the authenticated user has an allowed role.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $allowedRoles = array_map(
            fn(string $role): ?UserRole => UserRole::tryFrom($role),
            $roles
        );

        if (!in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
