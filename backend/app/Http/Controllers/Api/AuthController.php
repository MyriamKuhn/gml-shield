<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Jobs\RecordAuditLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    /**
     * Authenticate a user using Laravel's session guard.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (
            !Auth::attempt([
                ...$credentials,
                'is_active' => true,
            ])
        ) {
            try {
                RecordAuditLog::dispatch(
                    userId: null,
                    action: 'LOGIN_FAILED',
                    ipAddress: $request->ip(),
                    occurredAt: now()->toDateTimeString(),
                );
            } catch (Throwable $exception) {
                // Never expose credentials or authentication details.
                // An audit failure must not change the authentication decision.
                Log::error('Failed login audit dispatch failed.', [
                    'exception_type' => get_class($exception),
                ]);
            }

            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // Prevent session fixation after authentication.
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => $request->user(),
        ]);
    }

    /**
     * Log out and invalidate the current session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        // Invalidate the session and rotate the CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
