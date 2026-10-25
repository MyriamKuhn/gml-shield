<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_has_driver_role_by_default(): void
    {
        $user = User::factory()->create();

        $user->refresh();

        $this->assertSame(UserRole::DRIVER, $user->role);
    }

    public function test_user_role_is_cast_to_enum(): void
    {
        $user = User::factory()->create();

        $user->role = UserRole::DISPATCHER;
        $user->save();

        $user->refresh();

        $this->assertSame(UserRole::DISPATCHER, $user->role);
    }

    public function test_role_cannot_be_changed_by_mass_assignment(): void
    {
        $user = User::factory()->create();

        $user->fill([
            'name' => 'Test Driver',
            'role' => UserRole::ADMIN->value,
        ]);

        $user->save();
        $user->refresh();

        $this->assertSame('Test Driver', $user->name);
        $this->assertSame(UserRole::DRIVER, $user->role);
    }
}
