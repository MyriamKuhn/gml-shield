<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class MissionAssigned implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Broadcast only the information needed to identify
     * the newly assigned mission.
     *
     * Never include the encrypted or plaintext secret code.
     */
    public function __construct(
        public readonly int $missionId,
        public readonly int $driverId,
        public readonly string $title,
        public readonly string $reference,
    ) {
    }

    /**
     * Send this event exclusively to the assigned driver's
     * private channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('drivers.' . $this->driverId),
        ];
    }

    /**
     * Use the event name defined for GML-SHIELD.
     */
    public function broadcastAs(): string
    {
        return 'NEW_MISSION';
    }

    /**
     * Keep broadcasting jobs separate from audit jobs.
     */
    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    /**
     * Expose a minimal, explicit payload.
     */
    public function broadcastWith(): array
    {
        return [
            'mission_id' => $this->missionId,
            'title' => $this->title,
            'reference' => $this->reference,
        ];
    }
}
