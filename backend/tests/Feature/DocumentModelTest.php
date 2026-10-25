<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a valid mission for document relationship tests.
     */
    private function createMission(): Mission
    {
        $creator = User::factory()->create();

        $mission = new Mission([
            'reference' => 'GML-DOC-TEST-001',
            'title' => 'Livraison avec preuve',
            'pickup_address' => '10 rue de Paris, Metz',
            'delivery_address' => '20 rue de Lyon, Nancy',
            'scheduled_at' => '2026-11-15 09:00:00',
        ]);

        $mission->created_by = $creator->id;
        $mission->save();

        return $mission->refresh();
    }

    /**
     * A document must belong to its associated mission.
     */
    public function test_document_belongs_to_mission(): void
    {
        $mission = $this->createMission();

        $document = new Document([
            'file_path' => 'delivery-proofs/proof.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $document->mission()->associate($mission);
        $document->save();

        $this->assertTrue($document->mission->is($mission));
        $this->assertSame($mission->id, $document->mission_id);
    }

    /**
     * A mission can contain multiple delivery proofs.
     */
    public function test_mission_has_multiple_documents(): void
    {
        $mission = $this->createMission();

        $mission->documents()->create([
            'file_path' => 'delivery-proofs/proof-1.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $mission->documents()->create([
            'file_path' => 'delivery-proofs/proof-2.jpg',
            'mime_type' => 'image/jpeg',
            'uploaded_at' => now(),
        ]);

        $this->assertCount(2, $mission->documents);
    }

    /**
     * The mission identifier cannot be changed by mass assignment.
     */
    public function test_mission_id_is_not_mass_assignable(): void
    {
        $document = new Document();

        $document->fill([
            'file_path' => 'delivery-proofs/proof.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
            'mission_id' => 999,
        ]);

        $this->assertNull($document->mission_id);
    }
}
