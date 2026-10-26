<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Jobs\RecordAuditLog;
use Illuminate\Support\Facades\Queue;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_can_be_created_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $auditLog = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'MISSION_CREATED',
            'ip_address' => '192.0.2.10',
            'timestamp' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'user_id' => $user->id,
            'action' => 'MISSION_CREATED',
            'ip_address' => '192.0.2.10',
        ]);

        $this->assertTrue($auditLog->user->is($user));
    }

    public function test_audit_log_can_be_created_without_an_authenticated_user(): void
    {
        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'LOGIN_FAILED',
            'ip_address' => '192.0.2.20',
            'timestamp' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'user_id' => null,
            'action' => 'LOGIN_FAILED',
            'ip_address' => '192.0.2.20',
        ]);

        $this->assertNull($auditLog->user);
    }

    public function test_audit_log_preserves_its_action_ip_address_and_timestamp(): void
    {
        $timestamp = now()->startOfSecond();

        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'MISSION_UPDATED',
            'ip_address' => '2001:db8::1',
            'timestamp' => $timestamp,
        ]);

        $auditLog->refresh();

        $this->assertSame('MISSION_UPDATED', $auditLog->action);
        $this->assertSame('2001:db8::1', $auditLog->ip_address);
        $this->assertTrue($auditLog->timestamp->equalTo($timestamp));
    }

    public function test_record_audit_log_job_persists_an_audit_event(): void
    {
        $user = User::factory()->create();

        $occurredAt = now()->startOfSecond();

        $job = new RecordAuditLog(
            userId: $user->id,
            action: 'MISSION_CREATED',
            ipAddress: '192.0.2.10',
            occurredAt: $occurredAt->toDateTimeString(),
        );

        $job->handle();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'MISSION_CREATED',
            'ip_address' => '192.0.2.10',
            'timestamp' => $occurredAt->toDateTimeString(),
        ]);
    }

    public function test_record_audit_log_job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        RecordAuditLog::dispatch(
            userId: null,
            action: 'LOGIN_FAILED',
            ipAddress: '192.0.2.20',
            occurredAt: now()->toDateTimeString(),
        );

        Queue::assertPushed(RecordAuditLog::class, function (RecordAuditLog $job) {
            return $job->userId === null
                && $job->action === 'LOGIN_FAILED'
                && $job->ipAddress === '192.0.2.20';
        });

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_record_audit_log_job_is_idempotent(): void
    {
        $job = new RecordAuditLog(
            userId: null,
            action: 'AUDIT_IDEMPOTENCE_TEST',
            ipAddress: '192.0.2.10',
            occurredAt: now()->toDateTimeString(),
        );

        // First execution: the audit event is created.
        $job->handle();

        // Second execution: the same event must not be duplicated.
        $job->handle();

        $this->assertDatabaseCount('audit_logs', 1);

        $this->assertDatabaseHas('audit_logs', [
            'event_id' => $job->eventId,
            'action' => 'AUDIT_IDEMPOTENCE_TEST',
            'ip_address' => '192.0.2.10',
        ]);
    }
}
