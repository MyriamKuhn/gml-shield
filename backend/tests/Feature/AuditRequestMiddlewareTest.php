<?php

namespace Tests\Feature;

use App\Jobs\RecordAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AuditRequestMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_logout_dispatches_an_audit_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])
            ->withSession([])
            ->actingAs($user)
            ->postJson('/api/logout')
            ->assertOk();

        Queue::assertPushed(RecordAuditLog::class, 1);

        Queue::assertPushed(
            RecordAuditLog::class,
            fn(RecordAuditLog $job) =>
                str_contains($job->action, 'POST')
                && str_contains($job->action, 'logout')
                && str_contains($job->action, '[200]')
        );
    }

    public function test_get_request_does_not_dispatch_an_audit_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk();

        Queue::assertNotPushed(RecordAuditLog::class);
    }

    public function test_unauthenticated_modification_attempt_is_audited(): void
    {
        Queue::fake();

        $this->postJson('/api/missions', [])
            ->assertUnauthorized();

        Queue::assertPushed(
            RecordAuditLog::class,
            fn(RecordAuditLog $job) =>
                $job->userId === null
                && str_contains($job->action, 'POST')
                && str_contains($job->action, '[401]')
        );
    }

    public function test_failed_login_audit_does_not_expose_credentials(): void
    {
        Queue::fake();

        $password = 'SensitiveTestPassword123!';

        $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.test',
            'password' => $password,
        ])->assertUnprocessable();

        Queue::assertPushed(
            RecordAuditLog::class,
            function (RecordAuditLog $job) use ($password) {
                $payload = serialize($job);

                return $job->userId === null
                    && str_contains($job->action, 'POST')
                    && str_contains($job->action, '[422]')
                    && !str_contains($payload, $password)
                    && !str_contains($payload, 'unknown@example.test');
            }
        );
    }

    public function test_all_modifying_http_methods_dispatch_audit_jobs(): void
    {
        Queue::fake();

        Route::middleware('api')->match(
            ['POST', 'PATCH', 'PUT', 'DELETE'],
            '/api/audit-method-test',
            fn() => response()->json(['message' => 'OK'])
        );

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $response = $this->json(
                $method,
                '/api/audit-method-test'
            );

            $response->assertOk();

            Queue::assertPushed(
                RecordAuditLog::class,
                fn(RecordAuditLog $job) =>
                    str_starts_with($job->action, $method . ' ')
                    && str_contains($job->action, '[200]')
            );
        }

        Queue::assertPushed(RecordAuditLog::class, 4);
    }

    public function test_forbidden_modification_attempt_is_audited(): void
    {
        Queue::fake();

        $driver = User::factory()->create();

        $this->actingAs($driver)
            ->postJson('/api/missions', [
                'title' => 'Unauthorized mission',
            ])
            ->assertForbidden();

        Queue::assertPushed(
            RecordAuditLog::class,
            fn(RecordAuditLog $job) =>
                $job->userId === $driver->id
                && str_contains($job->action, 'POST')
                && str_contains($job->action, '[403]')
        );

        Queue::assertPushed(RecordAuditLog::class, 1);
    }

    public function test_audit_job_does_not_contain_mission_secret(): void
    {
        Queue::fake();

        $driver = User::factory()->create();

        $secretCode = 'CONFIDENTIAL_WAREHOUSE_CODE_123';

        $this->actingAs($driver)
            ->postJson('/api/missions', [
                'title' => 'Unauthorized mission',
                'secret_code' => $secretCode,
            ])
            ->assertForbidden();

        Queue::assertPushed(
            RecordAuditLog::class,
            function (RecordAuditLog $job) use ($secretCode) {
                $serializedJob = serialize($job);

                return !str_contains($serializedJob, $secretCode)
                    && !str_contains($job->action, $secretCode)
                    && $job->userId !== null;
            }
        );

        Queue::assertPushed(RecordAuditLog::class, 1);
    }

    public function test_failed_login_dispatches_explicit_audit_event(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ]);

        $response->assertUnprocessable();

        Queue::assertPushed(
            RecordAuditLog::class,
            function (RecordAuditLog $job): bool {
                return $job->action === 'LOGIN_FAILED'
                    && $job->userId === null
                    && !str_contains(
                        serialize($job),
                        'incorrect-password'
                    );
            }
        );
    }

    public function test_failed_login_remains_rejected_when_audit_queue_fails(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        Log::spy();

        Queue::shouldReceive('connection')
            ->with('database')
            ->andThrow(new RuntimeException('Audit queue unavailable'));

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/auth/login', [
                    'email' => $user->email,
                    'password' => 'incorrect-password',
                ]);

        $response->assertUnprocessable();
        $response->assertDontSee('incorrect-password');
        $response->assertDontSee('Audit queue unavailable');

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Failed login audit dispatch failed.'
                    && ($context['exception_type'] ?? null) === RuntimeException::class;
            })
            ->once();
    }
}
