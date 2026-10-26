<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class RecordAuditLog implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public readonly string $eventId;

    /**
     * Store only the metadata required for auditability.
     *
     * Never pass passwords, secret codes or request bodies
     * to this job because queued payloads are persisted.
     */
    public function __construct(
        public readonly ?int $userId,
        public readonly string $action,
        public readonly ?string $ipAddress,
        public readonly string $occurredAt,
    ) {
        $this->eventId = (string) Str::uuid();
        $this->onConnection('database');
        $this->onQueue('audit');
    }

    /**
     * Persist the audit event using its original timestamp.
     */
    public function handle(): void
    {
        AuditLog::query()->firstOrCreate(
            [
                'event_id' => $this->eventId,
            ],
            [
                'user_id' => $this->userId,
                'action' => $this->action,
                'ip_address' => $this->ipAddress,
                'timestamp' => $this->occurredAt,
            ]
        );
    }
}
