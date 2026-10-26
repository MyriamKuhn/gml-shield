<?php

namespace Tests\Unit;

use App\Events\MissionAssigned;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\TestCase;

class MissionAssignedTest extends TestCase
{
    private function createEvent(): MissionAssigned
    {
        return new MissionAssigned(
            missionId: 42,
            driverId: 7,
            title: 'Livraison médicale',
            reference: 'GML-TEST-042',
        );
    }

    public function test_event_targets_only_the_assigned_driver(): void
    {
        $channels = $this->createEvent()->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-drivers.7', $channels[0]->name);
    }

    public function test_event_uses_expected_name_and_queue(): void
    {
        $event = $this->createEvent();

        $this->assertSame('NEW_MISSION', $event->broadcastAs());
        $this->assertSame('broadcasts', $event->broadcastQueue());
    }

    public function test_event_payload_contains_no_secret(): void
    {
        $payload = $this->createEvent()->broadcastWith();

        $this->assertSame([
            'mission_id' => 42,
            'title' => 'Livraison médicale',
            'reference' => 'GML-TEST-042',
        ], $payload);

        $this->assertArrayNotHasKey('secret_code', $payload);
        $this->assertArrayNotHasKey('driver_id', $payload);
    }
}
