<?php

namespace Tests\Feature;

use App\Enums\MissionStatus;
use App\Enums\UserRole;
use App\Jobs\RecordAuditLog;
use App\Models\Mission;
use App\Models\User;
use App\Services\SecretCodeEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SecretCodeAuditTest extends TestCase
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

    private function createMissionWithSecret(User $driver): Mission
    {
        $dispatcher = User::factory()->create();
        $dispatcher->role = UserRole::DISPATCHER;
        $dispatcher->save();

        $encryptionService = app(
            SecretCodeEncryptionService::class
        );

        $mission = new Mission([
            'reference' => 'AUDIT-SECRET-' . uniqid(),
            'title' => 'Audit secret test mission',
            'pickup_address' => 'Lyon',
            'delivery_address' => 'Villeurbanne',
            'scheduled_at' => now()->addDay(),
        ]);

        $mission->created_by = $dispatcher->id;
        $mission->driver_id = $driver->id;
        $mission->status = MissionStatus::IN_PROGRESS;

        $mission->secret_code = $encryptionService->encrypt(
            'WAREHOUSE_SECRET_123'
        );

        $mission->save();

        return $mission;
    }

    public function test_authorized_secret_revelation_is_audited(): void
    {
        Queue::fake();

        $driver = User::factory()->create();
        $driver->role = UserRole::DRIVER;
        $driver->save();

        $mission = $this->createMissionWithSecret($driver);

        $this->actingAs($driver)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertOk()
            ->assertJsonPath(
                'secret_code',
                'WAREHOUSE_SECRET_123'
            );

        Queue::assertPushed(
            RecordAuditLog::class,
            function (RecordAuditLog $job) use ($driver) {
                return $job->userId === $driver->id
                    && $job->action === 'SECRET_CODE_REVEALED'
                    && !str_contains(
                        serialize($job),
                        'WAREHOUSE_SECRET_123'
                    );
            }
        );

        Queue::assertPushed(RecordAuditLog::class, 1);
    }

    public function test_unauthorized_secret_revelation_is_refused(): void
    {
        Queue::fake();

        $assignedDriver = User::factory()->create();
        $assignedDriver->role = UserRole::DRIVER;
        $assignedDriver->save();

        $otherDriver = User::factory()->create();
        $otherDriver->role = UserRole::DRIVER;
        $otherDriver->save();

        $mission = $this->createMissionWithSecret(
            $assignedDriver
        );

        $this->actingAs($otherDriver)
            ->getJson("/api/missions/{$mission->id}/secret-code")
            ->assertForbidden()
            ->assertDontSee('WAREHOUSE_SECRET_123');

        Queue::assertPushed(
            RecordAuditLog::class,
            function (RecordAuditLog $job) use ($otherDriver): bool {
                return $job->userId === $otherDriver->id
                    && $job->action === 'SECRET_CODE_REVEAL_DENIED'
                    && !str_contains(
                        serialize($job),
                        'WAREHOUSE_SECRET_123'
                    );
            }
        );

        Queue::assertPushed(RecordAuditLog::class, 1);
    }

    public function test_denied_secret_access_remains_forbidden_when_audit_queue_fails(): void
    {
        $assignedDriver = User::factory()->create();
        $assignedDriver->role = UserRole::DRIVER;
        $assignedDriver->save();

        $otherDriver = User::factory()->create();
        $otherDriver->role = UserRole::DRIVER;
        $otherDriver->save();

        $mission = $this->createMissionWithSecret($assignedDriver);

        Log::spy();

        Queue::shouldReceive('connection')
            ->with('database')
            ->andThrow(new RuntimeException('Audit queue unavailable'));

        $response = $this->actingAs($otherDriver)
            ->getJson("/api/missions/{$mission->id}/secret-code");

        $response->assertForbidden();
        $response->assertDontSee('WAREHOUSE_SECRET_123');
        $response->assertDontSee('Audit queue unavailable');

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Denied secret access audit dispatch failed.'
                    && ($context['exception_type'] ?? null) === RuntimeException::class
                    && !str_contains(
                        serialize($context),
                        'WAREHOUSE_SECRET_123'
                    );
            });
    }
}
