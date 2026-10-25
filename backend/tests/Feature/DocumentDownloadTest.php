<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * Create an active user with the requested role.
     */
    private function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    /**
     * Create a mission owned by the specified dispatcher.
     */
    private function createMission(
        User $dispatcher,
        string $reference = 'GML-DOWNLOAD-001'
    ): Mission {
        $mission = new Mission([
            'reference' => $reference,
            'title' => 'Livraison avec document',
            'pickup_address' => 'Metz',
            'delivery_address' => 'Nancy',
            'scheduled_at' => '2026-11-15 09:00:00',
        ]);

        $mission->created_by = $dispatcher->id;
        $mission->save();

        return $mission->refresh();
    }

    /**
     * Create a private delivery proof associated with a mission.
     */
    private function createDocument(Mission $mission)
    {
        $path = 'delivery-proofs/test-proof.pdf';

        Storage::disk('local')->put(
            $path,
            '%PDF-1.4 test document'
        );

        return $mission->documents()->create([
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);
    }

    public function test_owning_dispatcher_can_download_document(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher);
        $document = $this->createDocument($mission);

        $this->actingAs($dispatcher)
            ->get("/api/missions/{$mission->id}/documents/{$document->id}")
            ->assertOk()
            ->assertDownload('delivery-proof-' . $document->id . '.pdf');
    }

    public function test_other_dispatcher_cannot_download_document(): void
    {
        $owner = $this->createUser(UserRole::DISPATCHER);
        $otherDispatcher = $this->createUser(UserRole::DISPATCHER);

        $mission = $this->createMission($owner);
        $document = $this->createDocument($mission);

        $this->actingAs($otherDispatcher)
            ->getJson("/api/missions/{$mission->id}/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_driver_cannot_download_document(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createMission($dispatcher);
        $document = $this->createDocument($mission);

        $this->actingAs($driver)
            ->getJson("/api/missions/{$mission->id}/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_admin_cannot_download_document(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $admin = $this->createUser(UserRole::ADMIN);

        $mission = $this->createMission($dispatcher);
        $document = $this->createDocument($mission);

        $this->actingAs($admin)
            ->getJson("/api/missions/{$mission->id}/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_guest_cannot_download_document(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher);
        $document = $this->createDocument($mission);

        $this->getJson(
            "/api/missions/{$mission->id}/documents/{$document->id}"
        )->assertUnauthorized();
    }

    public function test_document_from_another_mission_is_rejected(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);

        $firstMission = $this->createMission(
            $dispatcher,
            'GML-DOWNLOAD-001'
        );

        $secondMission = $this->createMission(
            $dispatcher,
            'GML-DOWNLOAD-002'
        );

        $document = $this->createDocument($firstMission);

        $this->actingAs($dispatcher)
            ->getJson(
                "/api/missions/{$secondMission->id}/documents/{$document->id}"
            )
            ->assertNotFound();
    }

    public function test_missing_private_file_returns_not_found(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher);
        $document = $this->createDocument($mission);

        Storage::disk('local')->delete($document->file_path);

        $this->actingAs($dispatcher)
            ->getJson(
                "/api/missions/{$mission->id}/documents/{$document->id}"
            )
            ->assertNotFound();
    }

    public function test_path_traversal_is_rejected(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher);
        $document = $this->createDocument($mission);

        // Simulate a corrupted database record.
        $document->file_path = '../private-secret.pdf';
        $document->save();

        $this->actingAs($dispatcher)
            ->getJson(
                "/api/missions/{$mission->id}/documents/{$document->id}"
            )
            ->assertNotFound();
    }

    public function test_unexpected_storage_directory_is_rejected(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $mission = $this->createMission($dispatcher);
        $document = $this->createDocument($mission);

        // The file exists on the private disk,
        // but outside the authorized delivery-proofs directory.
        Storage::disk('local')->put(
            'other-documents/private.pdf',
            'Private content'
        );

        $document->file_path = 'other-documents/private.pdf';
        $document->save();

        $this->actingAs($dispatcher)
            ->getJson(
                "/api/missions/{$mission->id}/documents/{$document->id}"
            )
            ->assertNotFound();
    }
}
