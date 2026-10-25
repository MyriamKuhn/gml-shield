<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unauthenticated users cannot access the driver list.
     */
    public function test_guest_cannot_list_drivers(): void
    {
        $this->getJson('/api/drivers')
            ->assertUnauthorized();
    }

    /**
     * Drivers cannot access the driver selection list.
     */
    public function test_driver_cannot_list_drivers(): void
    {
        $driver = User::factory()->create([
            'role' => UserRole::DRIVER,
        ]);

        $this->actingAs($driver)
            ->getJson('/api/drivers')
            ->assertForbidden();
    }

    /**
     * Dispatchers can list users with the DRIVER role.
     */
    public function test_dispatcher_can_list_drivers(): void
    {
        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
        ]);

        $driver = User::factory()->create([
            'name' => 'Chauffeur Demo',
            'role' => UserRole::DRIVER,
        ]);

        $this->actingAs($dispatcher)
            ->getJson('/api/drivers')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $driver->id,
                'name' => 'Chauffeur Demo',
                'email' => $driver->email,
            ])
            ->assertJsonMissingPath('0.password')
            ->assertJsonMissingPath('0.remember_token');
    }

    /**
     * Administrators can access the driver list.
     */
    public function test_admin_can_list_drivers(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        User::factory()->create([
            'role' => UserRole::DRIVER,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/drivers')
            ->assertOk()
            ->assertJsonCount(1);
    }

    /**
     * The endpoint returns drivers only, excluding other user roles.
     */
    public function test_driver_list_excludes_admins_and_dispatchers(): void
    {
        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
        ]);

        User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        User::factory()->create([
            'role' => UserRole::DRIVER,
        ]);

        User::factory()->create([
            'role' => UserRole::DRIVER,
        ]);

        $this->actingAs($dispatcher)
            ->getJson('/api/drivers')
            ->assertOk()
            ->assertJsonCount(2);
    }

    /**
     * Drivers are returned in alphabetical order.
     */
    public function test_driver_list_is_sorted_by_name(): void
    {
        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
        ]);

        User::factory()->create([
            'name' => 'Zoé',
            'role' => UserRole::DRIVER,
        ]);

        User::factory()->create([
            'name' => 'Alice',
            'role' => UserRole::DRIVER,
        ]);

        $this->actingAs($dispatcher)
            ->getJson('/api/drivers')
            ->assertOk()
            ->assertJsonPath('0.name', 'Alice')
            ->assertJsonPath('1.name', 'Zoé');
    }
}
