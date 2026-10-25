<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Session\Middleware\StartSession;

class UserActivationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Disabled users must not authenticate with valid credentials.
     */
    public function test_disabled_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.test',
            'password' => 'SecurePassword123!',
            'is_active' => false,
        ]);

        $response = $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/auth/login', [
                'email' => 'active@example.test',
                'password' => 'SecurePassword123!',
            ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    /**
     * Active users must retain their existing login behavior.
     */
    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.test',
            'password' => 'SecurePassword123!',
            'is_active' => true,
        ]);

        $response = $this
            ->withServerVariables([
                'HTTP_ORIGIN' => 'http://localhost:5173',
                'HTTP_REFERER' => 'http://localhost:5173/',
            ])
            ->postJson('/api/auth/login', [
                'email' => 'active@example.test',
                'password' => 'SecurePassword123!',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Disabling an account must also revoke access
     * through an already authenticated session.
     */
    public function test_disabled_user_cannot_access_protected_api(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $user->is_active = false;
        $user->save();

        $user->refresh();

        $this->getJson('/api/missions')
            ->assertForbidden();
    }

    /**
     * Disabled drivers must not appear in assignment choices.
     */
    public function test_disabled_drivers_are_not_listed(): void
    {
        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
            'is_active' => true,
        ]);

        $activeDriver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $disabledDriver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => false,
        ]);

        $this->actingAs($dispatcher);

        $response = $this->getJson('/api/drivers');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $activeDriver->id,
            ])
            ->assertJsonMissing([
                'id' => $disabledDriver->id,
            ]);
    }

    /**
     * The Studi authentication endpoint must accept active users.
     */
    public function test_studi_login_endpoint_authenticates_active_user(): void
    {
        $user = User::factory()->create([
            'email' => 'studi@example.test',
            'password' => 'SecurePassword123!',
            'is_active' => true,
        ]);

        $this->withServerVariables([
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_REFERER' => 'http://localhost:5173/',
        ])->postJson('/api/auth/login', [
                    'email' => 'studi@example.test',
                    'password' => 'SecurePassword123!',
                ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }
}
