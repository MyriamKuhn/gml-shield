<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List users for active administrators only.
     *
     * Never expose passwords or authentication tokens.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (
            !$admin
            || !$admin->is_active
            || $admin->role !== UserRole::ADMIN
        ) {
            abort(403);
        }

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'role',
                'is_active',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);

        return response()->json($users);
    }

    /**
     * Create a driver or dispatcher account.
     *
     * Only active administrators can create accounts.
     * ADMIN accounts cannot be created through this endpoint.
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (
            !$admin
            || !$admin->is_active
            || $admin->role !== UserRole::ADMIN
        ) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:255',
            ],
            'role' => [
                'required',
                Rule::in([
                    UserRole::DRIVER->value,
                    UserRole::DISPATCHER->value,
                ]),
            ],
        ]);

        $user = new User();

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // The User model applies Laravel's configured password hashing.
        $user->password = $validated['password'];

        // Role and activation are assigned explicitly, not by mass assignment.
        $user->role = UserRole::from($validated['role']);
        $user->is_active = true;

        $user->save();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ], 201);
    }

    /**
     * Activate or deactivate a driver or dispatcher account.
     *
     * Only active administrators may change account status.
     * Administrator accounts are protected from this operation.
     */
    public function updateActive(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();

        if (
            !$admin
            || !$admin->is_active
            || $admin->role !== UserRole::ADMIN
        ) {
            abort(403);
        }

        // Administrator accounts cannot be modified through this endpoint.
        // This also prevents administrators from disabling themselves.
        if ($user->role === UserRole::ADMIN) {
            abort(403);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->is_active = $validated['is_active'];
        $user->save();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ]);
    }
}
