<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized();
    }

    public function test_active_admin_can_list_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_dispatcher_cannot_list_users(): void
    {
        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
            'is_active' => true,
        ]);

        $this->actingAs($dispatcher)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_driver_cannot_list_users(): void
    {
        $driver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $this->actingAs($driver)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_list_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_admin_receives_paginated_users_without_sensitive_fields(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        User::factory()->count(24)->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/users');

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('current_page', 1);

        foreach ($response->json('data') as $user) {
            $this->assertEqualsCanonicalizing(
                ['id', 'name', 'email', 'role', 'is_active'],
                array_keys($user)
            );
        }

        $response->assertDontSee('password');
        $response->assertDontSee('remember_token');
    }

    public function test_admin_can_create_driver_account(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Test Driver',
                'email' => 'driver.test@example.com',
                'password' => 'SecurePassword123!',
                'role' => 'DRIVER',
            ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Test Driver')
            ->assertJsonPath('email', 'driver.test@example.com')
            ->assertJsonPath('role', 'DRIVER')
            ->assertJsonPath('is_active', true)
            ->assertJsonMissingPath('password');

        $this->assertDatabaseHas('users', [
            'email' => 'driver.test@example.com',
            'role' => 'DRIVER',
            'is_active' => true,
        ]);

        $createdUser = User::query()
            ->where('email', 'driver.test@example.com')
            ->firstOrFail();

        $this->assertTrue(
            password_verify('SecurePassword123!', $createdUser->password)
        );

        $this->assertTrue(
            str_starts_with($createdUser->password, '$argon2id$')
        );
    }

    public function test_admin_cannot_create_another_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Another Admin',
                'email' => 'another.admin@example.com',
                'password' => 'SecurePassword123!',
                'role' => 'ADMIN',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('users', [
            'email' => 'another.admin@example.com',
        ]);
    }

    public function test_dispatcher_cannot_create_users(): void
    {
        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
            'is_active' => true,
        ]);

        $this->actingAs($dispatcher)
            ->postJson('/api/users', [
                'name' => 'Unauthorized Driver',
                'email' => 'unauthorized@example.com',
                'password' => 'SecurePassword123!',
                'role' => 'DRIVER',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'unauthorized@example.com',
        ]);
    }

    public function test_admin_cannot_create_duplicate_email(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Duplicate User',
                'email' => 'existing@example.com',
                'password' => 'SecurePassword123!',
                'role' => 'DRIVER',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('users', 2);
    }

    public function test_admin_can_create_dispatcher_account(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Test Dispatcher',
                'email' => 'dispatcher.test@example.com',
                'password' => 'SecurePassword123!',
                'role' => 'DISPATCHER',
            ])
            ->assertCreated()
            ->assertJsonPath('role', 'DISPATCHER')
            ->assertJsonPath('is_active', true)
            ->assertJsonMissingPath('password');

        $this->assertDatabaseHas('users', [
            'email' => 'dispatcher.test@example.com',
            'role' => 'DISPATCHER',
        ]);
    }

    public function test_inactive_admin_cannot_create_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Blocked Driver',
                'email' => 'blocked.driver@example.com',
                'password' => 'SecurePassword123!',
                'role' => 'DRIVER',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked.driver@example.com',
        ]);
    }

    public function test_admin_cannot_create_user_with_short_password(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Weak Password User',
                'email' => 'weak.password@example.com',
                'password' => 'short',
                'role' => 'DRIVER',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'email' => 'weak.password@example.com',
        ]);
    }

    public function test_admin_can_deactivate_driver(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $driver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/users/{$driver->id}/active", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_reactivate_dispatcher(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/users/{$dispatcher->id}/active", [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('users', [
            'id' => $dispatcher->id,
            'is_active' => true,
        ]);
    }

    public function test_dispatcher_cannot_deactivate_user(): void
    {
        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
            'is_active' => true,
        ]);

        $driver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $this->actingAs($dispatcher)
            ->patchJson("/api/users/{$driver->id}/active", [
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/users/{$admin->id}/active", [
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_driver_cannot_change_user_active_status(): void
    {
        $driver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $target = User::factory()->create([
            'role' => UserRole::DISPATCHER,
            'is_active' => true,
        ]);

        $this->actingAs($driver)
            ->patchJson("/api/users/{$target->id}/active", [
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_active' => true,
        ]);
    }

    public function test_inactive_admin_cannot_change_user_active_status(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => false,
        ]);

        $driver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/users/{$driver->id}/active", [
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_change_another_admin_active_status(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $otherAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/users/{$otherAdmin->id}/active", [
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_must_provide_boolean_active_status(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $driver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/users/{$driver->id}/active", [
                'is_active' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'is_active' => true,
        ]);
    }
}
