<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Temporary test routes, not registered in the production API.
        Route::middleware(['api', 'auth:sanctum', 'role:ADMIN'])
            ->get('/api/test/admin', fn() => response()->json(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'role:ADMIN,DISPATCHER'])
            ->get('/api/test/management', fn() => response()->json(['ok' => true]));
    }

    public function test_guest_cannot_access_admin_route(): void
    {
        $this->getJson('/api/test/admin')
            ->assertUnauthorized();
    }

    public function test_driver_cannot_access_admin_route(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::DRIVER;
        $user->save();

        $this->actingAs($user)
            ->getJson('/api/test/admin')
            ->assertForbidden();
    }

    public function test_dispatcher_cannot_access_admin_route(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::DISPATCHER;
        $user->save();

        $this->actingAs($user)
            ->getJson('/api/test/admin')
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_route(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::ADMIN;
        $user->save();

        $this->actingAs($user)
            ->getJson('/api/test/admin')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_dispatcher_can_access_management_route(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::DISPATCHER;
        $user->save();

        $this->actingAs($user)
            ->getJson('/api/test/management')
            ->assertOk();
    }

    public function test_driver_cannot_access_management_route(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::DRIVER;
        $user->save();

        $this->actingAs($user)
            ->getJson('/api/test/management')
            ->assertForbidden();
    }
}
