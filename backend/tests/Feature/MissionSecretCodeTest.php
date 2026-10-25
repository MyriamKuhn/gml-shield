<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Mission;
use App\Models\User;
use App\Services\SecretCodeEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\MissionStatus;

class MissionSecretCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'security.secret_code_key',
            base64_encode(random_bytes(32))
        );
    }

    private function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function missionPayload(): array
    {
        return [
            'reference' => 'GML-SECRET-001',
            'title' => 'Livraison sécurisée',
            'pickup_address' => 'Metz',
            'delivery_address' => 'Nancy',
            'scheduled_at' => now()->addDays(7)->toIso8601String(),
            'secret_code' => 'ENTREPOT-4582',
        ];
    }

    private function createAssignedMission(
        User $dispatcher,
        User $driver
    ): Mission {
        $mission = new Mission([
            'reference' => 'GML-SECRET-ASSIGNED',
            'title' => 'Mission confidentielle',
            'pickup_address' => 'Metz',
            'delivery_address' => 'Nancy',
            'scheduled_at' => now()->addDays(7),
        ]);

        $mission->created_by = $dispatcher->id;
        $mission->driver_id = $driver->id;
        $mission->secret_code = app(
            SecretCodeEncryptionService::class
        )->encrypt('ENTREPOT-4582');

        $mission->save();

        return $mission->refresh();
    }

    public function test_secret_is_encrypted_before_storage(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);

        $this->actingAs($dispatcher)
            ->postJson('/api/missions', $this->missionPayload())
            ->assertCreated()
            ->assertDontSee('ENTREPOT-4582');

        $storedSecret = Mission::query()
            ->firstOrFail()
            ->getRawOriginal('secret_code');

        $this->assertIsString($storedSecret);
        $this->assertNotSame('ENTREPOT-4582', $storedSecret);

        $this->assertSame(
            'ENTREPOT-4582',
            app(SecretCodeEncryptionService::class)
                ->decrypt($storedSecret)
        );
    }

    public function test_secret_is_not_exposed_in_mission_responses(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        foreach ([$dispatcher, $driver] as $user) {
            $this->actingAs($user)
                ->getJson("/api/missions/{$mission->id}")
                ->assertOk()
                ->assertDontSee('ENTREPOT-4582')
                ->assertJsonMissingPath('secret_code');

            $this->actingAs($user)
                ->getJson('/api/missions')
                ->assertOk()
                ->assertDontSee('ENTREPOT-4582')
                ->assertJsonMissingPath('data.0.secret_code');
        }
    }

    public function test_another_driver_cannot_view_the_mission(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $assignedDriver = $this->createUser(UserRole::DRIVER);
        $otherDriver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $assignedDriver
        );

        $this->actingAs($otherDriver)
            ->getJson("/api/missions/{$mission->id}")
            ->assertForbidden();
    }

    public function test_admin_cannot_reveal_secret(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $admin = $this->createUser(UserRole::ADMIN);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        $this->actingAs($admin)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertForbidden();
    }

    public function test_assigned_driver_can_reveal_secret(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        $mission->status = MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($driver)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertOk()
            ->assertJsonPath('secret_code', 'ENTREPOT-4582')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    /**
     * The assigned driver may reveal the secret
     * only while the mission is in progress.
     */
    public function test_driver_cannot_reveal_secret_outside_in_progress(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        foreach ([
            MissionStatus::PENDING,
            MissionStatus::COMPLETED,
            MissionStatus::CANCELLED,
        ] as $status) {
            $mission->status = $status;
            $mission->save();

            $this->actingAs($driver)
                ->getJson("/api/missions/{$mission->id}/secret-code")
                ->assertForbidden()
                ->assertJsonMissingPath('secret_code');
        }
    }

    /**
     * Dispatchers can manage their missions but cannot
     * reveal warehouse access codes reserved for drivers.
     */
    public function test_dispatcher_cannot_reveal_secret(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        $this->actingAs($dispatcher)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertForbidden()
            ->assertDontSee('ENTREPOT-4582');
    }

    /**
     * A driver must never reveal another driver's secret.
     */
    public function test_other_driver_cannot_reveal_secret(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $assignedDriver = $this->createUser(UserRole::DRIVER);
        $otherDriver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $assignedDriver
        );

        $this->actingAs($otherDriver)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertForbidden()
            ->assertDontSee('ENTREPOT-4582');
    }

    /**
     * Disabled accounts must be rejected even when
     * they were previously assigned to a mission.
     */
    public function test_disabled_driver_cannot_reveal_secret(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        $driver->is_active = false;
        $driver->save();

        $this->actingAs($driver)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertForbidden()
            ->assertDontSee('ENTREPOT-4582');
    }

    /**
     * Existing missions may legitimately have no secret
     * because the database migration is nullable.
     */
    public function test_missing_secret_returns_not_found(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        $mission->secret_code = null;
        $mission->save();

        $mission->status = MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($driver)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertNotFound()
            ->assertJsonMissingPath('secret_code');
    }

    /**
     * A corrupted authentication tag must prevent
     * the encrypted secret from being decrypted.
     */
    public function test_corrupted_secret_is_rejected(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        $parts = explode(':', $mission->secret_code);

        $tag = base64_decode($parts[2], true);
        $tag[0] = chr(ord($tag[0]) ^ 1);

        $parts[2] = base64_encode($tag);

        $mission->secret_code = implode(':', $parts);
        $mission->save();

        $mission->status = MissionStatus::IN_PROGRESS;
        $mission->save();

        $this->actingAs($driver)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertStatus(500)
            ->assertJsonPath(
                'message',
                'Unable to retrieve the secret code.'
            )
            ->assertJsonMissingPath('secret_code')
            ->assertDontSee('ENTREPOT-4582');
    }

    /**
     * Updating a mission must not accept an unencrypted
     * secret through the generic update endpoint.
     */
    public function test_generic_update_cannot_store_plaintext_secret(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createAssignedMission(
            $dispatcher,
            $driver
        );

        $originalEncryptedSecret = $mission->secret_code;

        $this->actingAs($dispatcher)
            ->patchJson("/api/missions/{$mission->id}", [
                'secret_code' => 'NEW-PLAINTEXT-SECRET',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['secret_code']);

        $this->assertSame(
            $originalEncryptedSecret,
            $mission->fresh()->secret_code
        );
    }

    public function test_secret_code_is_required_when_creating_mission(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);

        $payload = $this->missionPayload();
        unset($payload['secret_code']);

        $this->actingAs($dispatcher)
            ->postJson('/api/missions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['secret_code']);

        $this->assertDatabaseCount('missions', 0);
    }
}
