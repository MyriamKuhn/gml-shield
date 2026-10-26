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
use RuntimeException;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;

class AuditFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_is_not_disclosed_when_audit_queue_fails(): void
    {
        config()->set(
            'security.secret_code_key',
            base64_encode(random_bytes(32))
        );

        $driver = User::factory()->create();
        $driver->role = UserRole::DRIVER;
        $driver->save();

        $dispatcher = User::factory()->create();
        $dispatcher->role = UserRole::DISPATCHER;
        $dispatcher->save();

        $mission = new Mission([
            'reference' => 'AUDIT-FAILURE-001',
            'title' => 'Audit failure test',
            'pickup_address' => 'Lyon',
            'delivery_address' => 'Villeurbanne',
            'scheduled_at' => now()->addDay(),
        ]);

        $mission->driver_id = $driver->id;
        $mission->created_by = $dispatcher->id;
        $mission->status = MissionStatus::IN_PROGRESS;

        $mission->secret_code = app(
            SecretCodeEncryptionService::class
        )->encrypt('SENSITIVE_WAREHOUSE_CODE');

        $mission->save();

        // Simulate a database queue failure without stopping PostgreSQL.
        Queue::shouldReceive('connection')
            ->with('database')
            ->andThrow(new RuntimeException('Audit queue unavailable'));

        $response = $this->actingAs($driver)
            ->getJson("/api/missions/{$mission->id}/secret-code");

        $response->assertStatus(503);

        $response->assertDontSee('SENSITIVE_WAREHOUSE_CODE');
        $response->assertDontSee('Audit queue unavailable');
    }

    public function test_ordinary_request_remains_successful_when_audit_queue_fails(): void
    {
        Log::spy();

        Queue::shouldReceive('connection')
            ->with('database')
            ->andThrow(new RuntimeException('Audit queue unavailable'));

        $user = User::factory()->create();

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])
            ->withSession([])
            ->actingAs($user)
            ->postJson('/api/logout');

        $response->assertOk();

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Audit dispatch failed.'
                    && ($context['exception_type'] ?? null) === RuntimeException::class
                    && !str_contains(
                        serialize($context),
                        'Audit queue unavailable'
                    );
            });
    }
}
