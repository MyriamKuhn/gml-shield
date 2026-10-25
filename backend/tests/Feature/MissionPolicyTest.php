<?php

namespace Tests\Feature;

use App\Enums\MissionStatus;
use App\Enums\UserRole;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function createMission(
        User $creator,
        ?User $driver = null,
        MissionStatus $status = MissionStatus::PENDING
    ): Mission {
        $mission = new Mission([
            'reference' => 'GML-POLICY-' . uniqid(),
            'title' => 'Mission de test',
            'pickup_address' => 'Metz',
            'delivery_address' => 'Nancy',
            'scheduled_at' => '2026-11-15 09:00:00',
        ]);

        $mission->created_by = $creator->id;
        $mission->driver_id = $driver?->id;
        $mission->status = $status;
        $mission->save();

        return $mission->refresh();
    }

    public function test_dispatcher_can_create_missions_but_driver_cannot(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $this->assertTrue(Gate::forUser($dispatcher)->allows('create', Mission::class));
        $this->assertFalse(Gate::forUser($driver)->allows('create', Mission::class));
    }

    public function test_admin_can_view_any_mission(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($admin, $driver);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $mission));
    }

    public function test_driver_can_view_own_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $this->assertTrue(Gate::forUser($driver)->allows('view', $mission));
    }

    public function test_driver_cannot_view_another_drivers_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $assignedDriver = $this->userWithRole(UserRole::DRIVER);
        $otherDriver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission($dispatcher, $assignedDriver);

        $this->assertFalse(Gate::forUser($otherDriver)->allows('view', $mission));
    }

    public function test_only_assigned_driver_can_start_pending_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $assignedDriver = $this->userWithRole(UserRole::DRIVER);
        $otherDriver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission($dispatcher, $assignedDriver);

        $this->assertTrue(Gate::forUser($assignedDriver)->allows('start', $mission));
        $this->assertFalse(Gate::forUser($otherDriver)->allows('start', $mission));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('start', $mission));
    }

    public function test_driver_can_complete_only_own_in_progress_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $otherDriver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            MissionStatus::IN_PROGRESS
        );

        $this->assertTrue(Gate::forUser($driver)->allows('complete', $mission));
        $this->assertFalse(Gate::forUser($otherDriver)->allows('complete', $mission));
    }

    public function test_completed_mission_cannot_be_modified_or_cancelled(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            MissionStatus::COMPLETED
        );

        $this->assertFalse(Gate::forUser($dispatcher)->allows('update', $mission));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('assign', $mission));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('cancel', $mission));
        $this->assertFalse(Gate::forUser($driver)->allows('start', $mission));
    }

    public function test_mission_deletion_is_forbidden(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);
        $mission = $this->createMission($admin);

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $mission));
    }
}
