<?php

namespace Tests\Feature;

use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a mission with its mandatory attributes.
     */
    private function createMission(User $creator): Mission
    {
        $mission = new Mission([
            'reference' => 'GML-TEST-001',
            'title' => 'Livraison de démonstration',
            'description' => 'Transport de matériel',
            'pickup_address' => '10 rue de Paris, Metz',
            'delivery_address' => '20 rue de Lyon, Nancy',
            'scheduled_at' => '2026-11-15 09:00:00',
        ]);

        $mission->created_by = $creator->id;
        $mission->save();

        return $mission->refresh();
    }

    public function test_new_mission_has_pending_status_by_default(): void
    {
        $creator = User::factory()->create();

        $mission = $this->createMission($creator);

        $this->assertSame(MissionStatus::PENDING, $mission->status);
    }

    public function test_mission_status_is_cast_to_enum(): void
    {
        $creator = User::factory()->create();
        $mission = $this->createMission($creator);

        $mission->status = MissionStatus::IN_PROGRESS;
        $mission->save();

        $mission->refresh();

        $this->assertSame(MissionStatus::IN_PROGRESS, $mission->status);
    }

    public function test_mission_has_creator_relationship(): void
    {
        $creator = User::factory()->create();
        $mission = $this->createMission($creator);

        $this->assertTrue($mission->creator->is($creator));
    }

    public function test_mission_has_driver_relationship(): void
    {
        $creator = User::factory()->create();
        $driver = User::factory()->create();

        $mission = $this->createMission($creator);
        $mission->driver_id = $driver->id;
        $mission->save();

        $mission->refresh();

        $this->assertTrue($mission->driver->is($driver));
    }

    public function test_sensitive_fields_cannot_be_mass_assigned(): void
    {
        $creator = User::factory()->create();
        $driver = User::factory()->create();

        $mission = $this->createMission($creator);

        $mission->fill([
            'title' => 'Titre modifié',
            'status' => MissionStatus::COMPLETED->value,
            'driver_id' => $driver->id,
            'created_by' => $driver->id,
        ]);

        $this->assertSame('Titre modifié', $mission->title);
        $this->assertSame(MissionStatus::PENDING, $mission->status);
        $this->assertNull($mission->driver_id);
        $this->assertSame($creator->id, $mission->created_by);
    }
}
