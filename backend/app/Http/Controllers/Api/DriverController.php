<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    /**
     * List drivers available for mission assignment.
     *
     * Only authenticated admins and dispatchers can access this endpoint.
     * Return only the user information required by the frontend.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (
            $user === null ||
            !in_array(
                $user->role,
                [UserRole::ADMIN, UserRole::DISPATCHER],
                true
            )
        ) {
            abort(403, 'You are not authorized to list drivers.');
        }

        $drivers = User::query()
            ->where('role', UserRole::DRIVER->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($drivers);
    }
}
