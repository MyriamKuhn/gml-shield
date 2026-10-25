<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionApiTest extends TestCase
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
        string $reference = 'GML-API-001'
    ): Mission {
        $mission = new Mission([
            'reference' => $reference,
            'title' => 'Mission de test',
            'pickup_address' => 'Metz',
            'delivery_address' => 'Nancy',
            'scheduled_at' => '2026-11-15 09:00:00',
        ]);

        $mission->created_by = $creator->id;
        $mission->driver_id = $driver?->id;
        $mission->save();

        return $mission->refresh();
    }

    public function test_guest_cannot_list_missions(): void
    {
        $this->getJson('/api/missions')
            ->assertUnauthorized();
    }

    public function test_admin_can_list_all_missions(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $first = $this->createMission($admin, $driver, 'GML-API-001');
        $second = $this->createMission($admin, null, 'GML-API-002');

        $this->actingAs($admin)
            ->getJson('/api/missions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $first->id])
            ->assertJsonFragment(['id' => $second->id]);
    }

    public function test_dispatcher_can_list_all_missions(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $this->createMission($dispatcher, $driver, 'GML-API-003');
        $this->createMission($dispatcher, null, 'GML-API-004');

        $this->actingAs($dispatcher)
            ->getJson('/api/missions')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_driver_only_sees_assigned_missions(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $otherDriver = $this->userWithRole(UserRole::DRIVER);

        $ownMission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-API-005'
        );

        $otherMission = $this->createMission(
            $dispatcher,
            $otherDriver,
            'GML-API-006'
        );

        $unassignedMission = $this->createMission(
            $dispatcher,
            null,
            'GML-API-007'
        );

        $this->actingAs($driver)
            ->getJson('/api/missions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownMission->id)
            ->assertDontSee($otherMission->reference)
            ->assertDontSee($unassignedMission->reference);
    }

    public function test_guest_cannot_view_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $mission = $this->createMission(
            $dispatcher,
            null,
            'GML-SHOW-001'
        );

        $this->getJson("/api/missions/{$mission->id}")
            ->assertUnauthorized();
    }

    public function test_admin_can_view_mission(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);
        $mission = $this->createMission(
            $admin,
            null,
            'GML-SHOW-002'
        );

        $this->actingAs($admin)
            ->getJson("/api/missions/{$mission->id}")
            ->assertOk()
            ->assertJsonPath('id', $mission->id)
            ->assertJsonPath('reference', $mission->reference);
    }

    public function test_dispatcher_can_view_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $mission = $this->createMission(
            $dispatcher,
            null,
            'GML-SHOW-003'
        );

        $this->actingAs($dispatcher)
            ->getJson("/api/missions/{$mission->id}")
            ->assertOk()
            ->assertJsonPath('id', $mission->id);
    }

    public function test_driver_can_view_own_mission_but_not_another_drivers_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $otherDriver = $this->userWithRole(UserRole::DRIVER);

        $ownMission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-SHOW-004'
        );

        $otherMission = $this->createMission(
            $dispatcher,
            $otherDriver,
            'GML-SHOW-005'
        );

        $this->actingAs($driver)
            ->getJson("/api/missions/{$ownMission->id}")
            ->assertOk()
            ->assertJsonPath('id', $ownMission->id);

        $this->actingAs($driver)
            ->getJson("/api/missions/{$otherMission->id}")
            ->assertForbidden();
    }

    public function test_nonexistent_mission_returns_not_found(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);

        $this->actingAs($admin)
            ->getJson('/api/missions/999999')
            ->assertNotFound();
    }

    /**
     * Valid data for creating a transport mission.
     */
    private function validMissionPayload(): array
    {
        return [
            'reference' => 'GML-CREATE-001',
            'title' => 'Livraison de matériel',
            'description' => 'Livraison sécurisée',
            'pickup_address' => '10 rue de Paris, Metz',
            'delivery_address' => '20 rue de Lyon, Nancy',
            'scheduled_at' => now()->addDays(7)->toIso8601String(),
        ];
    }

    public function test_guest_cannot_create_mission(): void
    {
        $this->postJson('/api/missions', $this->validMissionPayload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('missions', 0);
    }

    public function test_admin_can_create_mission(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);

        $this->actingAs($admin)
            ->postJson('/api/missions', $this->validMissionPayload())
            ->assertCreated()
            ->assertJsonPath('reference', 'GML-CREATE-001')
            ->assertJsonPath('status', 'PENDING')
            ->assertJsonPath('created_by', $admin->id)
            ->assertJsonPath('driver_id', null);

        $this->assertDatabaseCount('missions', 1);
    }

    public function test_dispatcher_can_create_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);

        $this->actingAs($dispatcher)
            ->postJson('/api/missions', $this->validMissionPayload())
            ->assertCreated()
            ->assertJsonPath('created_by', $dispatcher->id);

        $this->assertDatabaseCount('missions', 1);
    }

    public function test_driver_cannot_create_mission(): void
    {
        $driver = $this->userWithRole(UserRole::DRIVER);

        $this->actingAs($driver)
            ->postJson('/api/missions', $this->validMissionPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('missions', 0);
    }

    public function test_mission_creation_rejects_invalid_data(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);

        $this->actingAs($dispatcher)
            ->postJson('/api/missions', [
                'reference' => '',
                'title' => '',
                'pickup_address' => '',
                'delivery_address' => '',
                'scheduled_at' => now()->subDay()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reference',
                'title',
                'pickup_address',
                'delivery_address',
                'scheduled_at',
            ]);

        $this->assertDatabaseCount('missions', 0);
    }

    public function test_mission_creation_rejects_sensitive_fields(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $otherUser = $this->userWithRole(UserRole::DRIVER);

        $payload = array_merge($this->validMissionPayload(), [
            'status' => 'COMPLETED',
            'driver_id' => $otherUser->id,
            'created_by' => $otherUser->id,
        ]);

        $this->actingAs($dispatcher)
            ->postJson('/api/missions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'driver_id',
                'created_by',
            ]);

        $this->assertDatabaseCount('missions', 0);
    }

    public function test_mission_creation_rejects_duplicate_reference(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);

        $payload = $this->validMissionPayload();

        // Create the first mission.
        $this->actingAs($dispatcher)
            ->postJson('/api/missions', $payload)
            ->assertCreated();

        // Attempt to create a second mission with the same reference.
        $this->actingAs($dispatcher)
            ->postJson('/api/missions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);

        // Only the first mission must exist.
        $this->assertDatabaseCount('missions', 1);
    }

    public function test_guest_cannot_assign_driver(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher);

        $this->patchJson("/api/missions/{$mission->id}/assign", [
            'driver_id' => $driver->id,
        ])->assertUnauthorized();

        $this->assertNull($mission->fresh()->driver_id);
    }

    public function test_dispatcher_can_assign_driver_to_pending_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher);

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/assign", [
                'driver_id' => $driver->id,
            ])
            ->assertOk()
            ->assertJsonPath('driver_id', $driver->id)
            ->assertJsonPath('driver.id', $driver->id);

        $this->assertSame($driver->id, $mission->fresh()->driver_id);
    }

    public function test_admin_can_assign_driver(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($admin);

        $this->actingAs($admin)
            ->patchJson("/api/missions/{$mission->id}/assign", [
                'driver_id' => $driver->id,
            ])
            ->assertOk();

        $this->assertSame($driver->id, $mission->fresh()->driver_id);
    }

    public function test_driver_cannot_assign_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher);

        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/assign", [
                'driver_id' => $driver->id,
            ])
            ->assertForbidden();

        $this->assertNull($mission->fresh()->driver_id);
    }

    public function test_cannot_assign_non_driver_or_nonexistent_user(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $admin = $this->userWithRole(UserRole::ADMIN);
        $mission = $this->createMission($dispatcher);

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/assign", [
                'driver_id' => $admin->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_id']);

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/assign", [
                'driver_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_id']);

        $this->assertNull($mission->fresh()->driver_id);
    }

    public function test_cannot_assign_driver_to_completed_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher);

        $mission->status = \App\Enums\MissionStatus::COMPLETED;
        $mission->save();

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/assign", [
                'driver_id' => $driver->id,
            ])
            ->assertForbidden();

        $this->assertNull($mission->fresh()->driver_id);
    }

    public function test_dispatcher_can_reassign_pending_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $firstDriver = $this->userWithRole(UserRole::DRIVER);
        $secondDriver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $firstDriver,
            'GML-REASSIGN-001'
        );

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/assign", [
                'driver_id' => $secondDriver->id,
            ])
            ->assertOk()
            ->assertJsonPath('driver_id', $secondDriver->id);

        $this->assertSame(
            $secondDriver->id,
            $mission->fresh()->driver_id
        );
    }

    public function test_cannot_reassign_in_progress_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $firstDriver = $this->userWithRole(UserRole::DRIVER);
        $secondDriver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $firstDriver,
            'GML-REASSIGN-002'
        );

        $mission->status = \App\Enums\MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/assign", [
                'driver_id' => $secondDriver->id,
            ])
            ->assertForbidden();

        // The original driver must remain assigned.
        $this->assertSame(
            $firstDriver->id,
            $mission->fresh()->driver_id
        );
    }

    public function test_guest_cannot_start_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-START-001'
        );

        $this->patchJson("/api/missions/{$mission->id}/start", [])
            ->assertUnauthorized();

        $this->assertSame(
            \App\Enums\MissionStatus::PENDING,
            $mission->fresh()->status
        );
    }

    public function test_assigned_driver_can_start_pending_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-START-002'
        );

        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/start", [])
            ->assertOk()
            ->assertJsonPath('status', 'IN_PROGRESS')
            ->assertJsonPath('driver_id', $driver->id);

        $this->assertSame(
            \App\Enums\MissionStatus::IN_PROGRESS,
            $mission->fresh()->status
        );
    }

    public function test_other_driver_cannot_start_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $assignedDriver = $this->userWithRole(UserRole::DRIVER);
        $otherDriver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $assignedDriver,
            'GML-START-003'
        );

        $this->actingAs($otherDriver)
            ->patchJson("/api/missions/{$mission->id}/start", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::PENDING,
            $mission->fresh()->status
        );
    }

    public function test_manager_cannot_start_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $admin = $this->userWithRole(UserRole::ADMIN);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-START-004'
        );

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/start", [])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/api/missions/{$mission->id}/start", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::PENDING,
            $mission->fresh()->status
        );
    }

    public function test_mission_cannot_be_started_twice(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-START-005'
        );

        // First start succeeds.
        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/start", [])
            ->assertOk();

        // Second start must be rejected.
        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/start", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::IN_PROGRESS,
            $mission->fresh()->status
        );
    }

    public function test_guest_cannot_complete_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-COMPLETE-001'
        );

        $mission->status = \App\Enums\MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->patchJson("/api/missions/{$mission->id}/complete", [])
            ->assertUnauthorized();

        $this->assertSame(
            \App\Enums\MissionStatus::IN_PROGRESS,
            $mission->fresh()->status
        );
    }

    public function test_assigned_driver_can_complete_in_progress_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-COMPLETE-002'
        );

        $mission->status = \App\Enums\MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/complete", [])
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETED')
            ->assertJsonPath('driver_id', $driver->id);

        $this->assertSame(
            \App\Enums\MissionStatus::COMPLETED,
            $mission->fresh()->status
        );
    }

    public function test_other_driver_cannot_complete_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $assignedDriver = $this->userWithRole(UserRole::DRIVER);
        $otherDriver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $assignedDriver,
            'GML-COMPLETE-003'
        );

        $mission->status = \App\Enums\MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($otherDriver)
            ->patchJson("/api/missions/{$mission->id}/complete", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::IN_PROGRESS,
            $mission->fresh()->status
        );
    }

    public function test_manager_cannot_complete_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $admin = $this->userWithRole(UserRole::ADMIN);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-COMPLETE-004'
        );

        $mission->status = \App\Enums\MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/complete", [])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/api/missions/{$mission->id}/complete", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::IN_PROGRESS,
            $mission->fresh()->status
        );
    }

    public function test_pending_mission_cannot_be_completed(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-COMPLETE-005'
        );

        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/complete", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::PENDING,
            $mission->fresh()->status
        );
    }

    public function test_mission_cannot_be_completed_twice(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-COMPLETE-006'
        );

        $mission->status = \App\Enums\MissionStatus::IN_PROGRESS;
        $mission->save();

        // First completion succeeds.
        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/complete", [])
            ->assertOk();

        // Second completion is forbidden.
        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/complete", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::COMPLETED,
            $mission->fresh()->status
        );
    }

    public function test_guest_cannot_cancel_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $mission = $this->createMission(
            $dispatcher,
            null,
            'GML-CANCEL-001'
        );

        $this->patchJson("/api/missions/{$mission->id}/cancel", [])
            ->assertUnauthorized();

        $this->assertSame(
            \App\Enums\MissionStatus::PENDING,
            $mission->fresh()->status
        );
    }

    public function test_admin_can_cancel_pending_mission(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);

        $mission = $this->createMission(
            $admin,
            null,
            'GML-CANCEL-002'
        );

        $this->actingAs($admin)
            ->patchJson("/api/missions/{$mission->id}/cancel", [])
            ->assertOk()
            ->assertJsonPath('status', 'CANCELLED');

        $this->assertSame(
            \App\Enums\MissionStatus::CANCELLED,
            $mission->fresh()->status
        );
    }

    public function test_dispatcher_can_cancel_in_progress_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-CANCEL-003'
        );

        $mission->status = \App\Enums\MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/cancel", [])
            ->assertOk()
            ->assertJsonPath('status', 'CANCELLED');

        $this->assertSame(
            \App\Enums\MissionStatus::CANCELLED,
            $mission->fresh()->status
        );
    }

    public function test_driver_cannot_cancel_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-CANCEL-004'
        );

        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}/cancel", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::PENDING,
            $mission->fresh()->status
        );
    }

    public function test_completed_mission_cannot_be_cancelled(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            'GML-CANCEL-005'
        );

        $mission->status = \App\Enums\MissionStatus::COMPLETED;
        $mission->save();

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/cancel", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::COMPLETED,
            $mission->fresh()->status
        );
    }

    public function test_mission_cannot_be_cancelled_twice(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);

        $mission = $this->createMission(
            $dispatcher,
            null,
            'GML-CANCEL-006'
        );

        // First cancellation succeeds.
        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/cancel", [])
            ->assertOk();

        // Second cancellation must be refused.
        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}/cancel", [])
            ->assertForbidden();

        $this->assertSame(
            \App\Enums\MissionStatus::CANCELLED,
            $mission->fresh()->status
        );
    }

    public function test_guest_cannot_update_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher, null, 'GML-UPDATE-001');

        $this->patchJson("/api/missions/{$mission->id}", [
            'title' => 'Titre modifié',
        ])->assertUnauthorized();

        $this->assertSame('Mission de test', $mission->fresh()->title);
    }

    public function test_admin_can_update_pending_mission(): void
    {
        $admin = $this->userWithRole(UserRole::ADMIN);
        $mission = $this->createMission($admin, null, 'GML-UPDATE-002');

        $this->actingAs($admin)
            ->patchJson("/api/missions/{$mission->id}", [
                'title' => 'Nouvelle mission',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Nouvelle mission')
            ->assertJsonPath('status', 'PENDING');

        $this->assertSame('Nouvelle mission', $mission->fresh()->title);
    }

    public function test_dispatcher_can_update_pending_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher, null, 'GML-UPDATE-003');

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}", [
                'pickup_address' => 'Strasbourg',
                'delivery_address' => 'Colmar',
            ])
            ->assertOk()
            ->assertJsonPath('pickup_address', 'Strasbourg')
            ->assertJsonPath('delivery_address', 'Colmar');

        $this->assertSame('Strasbourg', $mission->fresh()->pickup_address);
        $this->assertSame('Colmar', $mission->fresh()->delivery_address);
    }

    public function test_driver_cannot_update_mission(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver, 'GML-UPDATE-004');

        $this->actingAs($driver)
            ->patchJson("/api/missions/{$mission->id}", [
                'title' => 'Modification interdite',
            ])
            ->assertForbidden();

        $this->assertSame('Mission de test', $mission->fresh()->title);
    }

    public function test_in_progress_mission_cannot_be_updated(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher, null, 'GML-UPDATE-005');

        $mission->status = \App\Enums\MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}", [
                'title' => 'Modification interdite',
            ])
            ->assertForbidden();

        $this->assertSame('Mission de test', $mission->fresh()->title);
    }

    public function test_completed_and_cancelled_missions_cannot_be_updated(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);

        foreach ([
            \App\Enums\MissionStatus::COMPLETED,
            \App\Enums\MissionStatus::CANCELLED,
        ] as $status) {
            $mission = $this->createMission(
                $dispatcher,
                null,
                'GML-UPDATE-' . $status->value
            );

            $mission->status = $status;
            $mission->save();

            $this->actingAs($dispatcher)
                ->patchJson("/api/missions/{$mission->id}", [
                    'title' => 'Modification interdite',
                ])
                ->assertForbidden();

            $this->assertSame('Mission de test', $mission->fresh()->title);
        }
    }

    public function test_update_rejects_sensitive_fields(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);
        $driver = $this->userWithRole(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, null, 'GML-UPDATE-007');

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}", [
                'title' => 'Modification non autorisée',
                'status' => 'COMPLETED',
                'driver_id' => $driver->id,
                'created_by' => $driver->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'driver_id',
                'created_by',
            ]);

        $this->assertSame('Mission de test', $mission->fresh()->title);
        $this->assertSame(
            \App\Enums\MissionStatus::PENDING,
            $mission->fresh()->status
        );
        $this->assertNull($mission->fresh()->driver_id);
        $this->assertSame($dispatcher->id, $mission->fresh()->created_by);
    }

    public function test_update_rejects_duplicate_reference(): void
    {
        $dispatcher = $this->userWithRole(UserRole::DISPATCHER);

        $first = $this->createMission($dispatcher, null, 'GML-UPDATE-008');
        $second = $this->createMission($dispatcher, null, 'GML-UPDATE-009');

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$second->id}", [
                'reference' => $first->reference,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);

        $this->assertSame('GML-UPDATE-009', $second->fresh()->reference);
    }
}
