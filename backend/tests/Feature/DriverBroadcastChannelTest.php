<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverBroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Enable the Reverb broadcaster only for channel authorization tests.
     *
     * Use dummy credentials so no real WebSocket server or
     * production secret is needed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.app_id', 'test-app');
        config()->set('broadcasting.connections.reverb.key', 'test-key');
        config()->set('broadcasting.connections.reverb.secret', 'test-secret');
        // Register channel authorization callbacks on the test broadcaster.
        require base_path('routes/channels.php');
    }

    /**
     * Authorize a driver only on their own private channel.
     *
     * The authorization endpoint must never trust the
     * driver identifier supplied by the client.
     */
    public function test_active_driver_can_subscribe_to_own_channel(): void
    {
        $driver = $this->createUser(UserRole::DRIVER);

        // Verify the driver attributes expected by the channel policy.
        $this->assertTrue($driver->is_active);
        $this->assertSame(UserRole::DRIVER, $driver->role);
        $this->assertIsInt($driver->id);

        // Authenticate before checking the session and channel access.
        $this->actingAs($driver, 'web');

        $this->assertAuthenticatedAs($driver, 'web');

        $this->assertTrue(
            app('auth')->guard('sanctum')->check(),
            'Sanctum ne reconnaît pas le chauffeur authentifié.'
        );

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-drivers.' . $driver->id,
        ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_driver_cannot_subscribe_to_another_drivers_channel(): void
    {
        $driver = $this->createUser(UserRole::DRIVER);
        $otherDriver = $this->createUser(UserRole::DRIVER);

        $this->actingAs($driver)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-drivers.' . $otherDriver->id,
            ])
            ->assertForbidden();
    }

    public function test_dispatcher_cannot_subscribe_to_driver_channel(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $this->actingAs($dispatcher)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-drivers.' . $driver->id,
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_subscribe_to_driver_channel(): void
    {
        $admin = $this->createUser(UserRole::ADMIN);
        $driver = $this->createUser(UserRole::DRIVER);

        $this->actingAs($admin)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-drivers.' . $driver->id,
            ])
            ->assertForbidden();
    }

    public function test_inactive_driver_cannot_subscribe_to_own_channel(): void
    {
        $driver = $this->createUser(UserRole::DRIVER, false);

        $this->actingAs($driver)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-drivers.' . $driver->id,
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_subscribe_to_driver_channel(): void
    {
        $driver = $this->createUser(UserRole::DRIVER);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-drivers.' . $driver->id,
        ])->assertUnauthorized();
    }

    /**
     * Create a user with an explicit role and activation status.
     *
     * These attributes are deliberately assigned after creation
     * because the User model prevents their mass assignment.
     */
    private function createUser(UserRole $role, bool $isActive = true): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->is_active = $isActive;
        $user->save();

        return $user;
    }
}
