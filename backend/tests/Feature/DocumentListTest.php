<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentListTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createMission(
        User $creator,
        string $reference = 'GML-LIST-001'
    ): Mission {
        $mission = new Mission([
            'reference' => $reference,
            'title' => 'Mission avec preuves',
            'pickup_address' => 'Metz',
            'delivery_address' => 'Nancy',
            'scheduled_at' => '2026-11-15 09:00:00',
        ]);

        $mission->created_by = $creator->id;
        $mission->save();

        return $mission->refresh();
    }

    public function test_owning_dispatcher_can_list_documents(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher);

        $document = $mission->documents()->create([
            'file_path' => 'delivery-proofs/private-proof.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($dispatcher)
            ->getJson("/api/missions/{$mission->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonMissingPath('data.0.file_path')
            ->assertDontSee('private-proof.pdf');
    }

    public function test_other_dispatcher_cannot_list_documents(): void
    {
        $owner = $this->createUser(UserRole::DISPATCHER);
        $other = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($owner);

        $this->actingAs($other)
            ->getJson("/api/missions/{$mission->id}/documents")
            ->assertForbidden();
    }

    public function test_driver_cannot_list_documents(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher);

        $this->actingAs($driver)
            ->getJson("/api/missions/{$mission->id}/documents")
            ->assertForbidden();
    }

    public function test_admin_cannot_list_documents(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $admin = $this->createUser(UserRole::ADMIN);
        $mission = $this->createMission($dispatcher);

        $this->actingAs($admin)
            ->getJson("/api/missions/{$mission->id}/documents")
            ->assertForbidden();
    }

    public function test_guest_cannot_list_documents(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher);

        $this->getJson("/api/missions/{$mission->id}/documents")
            ->assertUnauthorized();
    }

    public function test_dispatcher_only_sees_documents_of_requested_mission(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);

        $firstMission = $this->createMission(
            $dispatcher,
            'GML-LIST-001'
        );

        $secondMission = $this->createMission(
            $dispatcher,
            'GML-LIST-002'
        );

        $firstDocument = $firstMission->documents()->create([
            'file_path' => 'delivery-proofs/first.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $secondDocument = $secondMission->documents()->create([
            'file_path' => 'delivery-proofs/second.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($dispatcher)
            ->getJson("/api/missions/{$firstMission->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstDocument->id)
            ->assertDontSee('second.pdf');

        $this->assertNotEquals(
            $firstDocument->id,
            $secondDocument->id
        );
    }

    public function test_mission_without_documents_returns_empty_list(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher);

        $this->actingAs($dispatcher)
            ->getJson("/api/missions/{$mission->id}/documents")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
