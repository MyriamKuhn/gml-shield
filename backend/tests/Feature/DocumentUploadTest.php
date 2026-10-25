<?php

namespace Tests\Feature;

use App\Enums\MissionStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Filesystem\FilesystemAdapter;
use Mockery;
use RuntimeException;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createMission(
        User $creator,
        User $driver,
        MissionStatus $status = MissionStatus::IN_PROGRESS
    ): Mission {
        $mission = new Mission([
            'reference' => 'GML-UPLOAD-001',
            'title' => 'Livraison avec preuve',
            'pickup_address' => 'Metz',
            'delivery_address' => 'Nancy',
            'scheduled_at' => '2026-11-15 09:00:00',
        ]);

        $mission->created_by = $creator->id;
        $mission->driver_id = $driver->id;
        $mission->status = $status;
        $mission->save();

        return $mission->refresh();
    }

    public function test_assigned_driver_can_upload_pdf(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $file = UploadedFile::fake()->create(
            'proof.pdf',
            100,
            'application/pdf'
        );

        $response = $this->actingAs($driver)->post(
            "/api/missions/{$mission->id}/documents",
            ['file' => $file],
            ['Accept' => 'application/json']
        );

        $response->assertCreated()
            ->assertJsonPath('mission_id', $mission->id)
            ->assertJsonPath('mime_type', 'application/pdf')
            ->assertJsonMissingPath('file_path');

        $this->assertDatabaseCount('documents', 1);

        $document = Document::query()->firstOrFail();

        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_other_driver_cannot_upload(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $assignedDriver = $this->createUser(UserRole::DRIVER);
        $otherDriver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $assignedDriver);

        $response = $this->actingAs($otherDriver)->post(
            "/api/missions/{$mission->id}/documents",
            [
                'file' => UploadedFile::fake()->create(
                    'proof.pdf',
                    100,
                    'application/pdf'
                ),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_dispatcher_cannot_upload(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $response = $this->actingAs($dispatcher)->post(
            "/api/missions/{$mission->id}/documents",
            [
                'file' => UploadedFile::fake()->create(
                    'proof.pdf',
                    100,
                    'application/pdf'
                ),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_driver_cannot_upload_to_pending_mission(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            MissionStatus::PENDING
        );

        $response = $this->actingAs($driver)->post(
            "/api/missions/{$mission->id}/documents",
            [
                'file' => UploadedFile::fake()->create(
                    'proof.pdf',
                    100,
                    'application/pdf'
                ),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $response = $this->actingAs($driver)->post(
            "/api/missions/{$mission->id}/documents",
            [
                'file' => UploadedFile::fake()->create(
                    'script.txt',
                    10,
                    'text/plain'
                ),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_file_larger_than_ten_megabytes_is_rejected(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $response = $this->actingAs($driver)->post(
            "/api/missions/{$mission->id}/documents",
            [
                'file' => UploadedFile::fake()->create(
                    'large.pdf',
                    11000,
                    'application/pdf'
                ),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_guest_cannot_upload(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $this->postJson(
            "/api/missions/{$mission->id}/documents",
            []
        )->assertUnauthorized();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_completed_mission_rejects_upload(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            MissionStatus::COMPLETED
        );

        $this->actingAs($driver)->postJson(
            "/api/missions/{$mission->id}/documents",
            []
        )->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_cancelled_mission_rejects_upload(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);

        $mission = $this->createMission(
            $dispatcher,
            $driver,
            MissionStatus::CANCELLED
        );

        $this->actingAs($driver)->postJson(
            "/api/missions/{$mission->id}/documents",
            []
        )->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_missing_file_is_rejected(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $this->actingAs($driver)->postJson(
            "/api/missions/{$mission->id}/documents",
            []
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_fake_pdf_content_is_rejected(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        // Create a real temporary file with misleading extension.
        $temporaryPath = tempnam(sys_get_temp_dir(), 'gml_pdf_');

        if ($temporaryPath === false) {
            $this->fail('Unable to create temporary test file.');
        }

        try {
            file_put_contents(
                $temporaryPath,
                'This is plain text, not a valid PDF document.'
            );

            // The client claims PDF, but the actual content is text.
            $file = new SymfonyUploadedFile(
                $temporaryPath,
                'proof.pdf',
                'application/pdf',
                null,
                true
            );

            $this->actingAs($driver)->post(
                "/api/missions/{$mission->id}/documents",
                ['file' => $file],
                ['Accept' => 'application/json']
            )
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');

            $this->assertDatabaseCount('documents', 0);
        } finally {
            if (file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function test_admin_cannot_upload(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $admin = $this->createUser(UserRole::ADMIN);
        $mission = $this->createMission($dispatcher, $driver);

        $this->actingAs($admin)->postJson(
            "/api/missions/{$mission->id}/documents",
            []
        )->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
    }

    /**
     * A failed filesystem write must not create a document record.
     *
     * Simulate a disk adapter returning false during storage.
     */
    public function test_failed_file_storage_does_not_create_document(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $file = UploadedFile::fake()->create(
            'proof.pdf',
            100,
            'application/pdf'
        );

        $disk = Mockery::mock(FilesystemAdapter::class);

        $disk->shouldReceive('putFileAs')
            ->once()
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($driver)->post(
                "/api/missions/{$mission->id}/documents",
                ['file' => $file],
                ['Accept' => 'application/json']
            );

            $this->fail('The failed storage operation should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Unable to store the delivery proof.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('documents', 0);
    }

    /**
     * A database failure after file storage must remove
     * the uploaded file to avoid leaving an orphaned proof.
     */
    public function test_database_failure_removes_stored_file(): void
    {
        $dispatcher = $this->createUser(UserRole::DISPATCHER);
        $driver = $this->createUser(UserRole::DRIVER);
        $mission = $this->createMission($dispatcher, $driver);

        $file = UploadedFile::fake()->create(
            'proof.pdf',
            100,
            'application/pdf'
        );

        // Force the database registration to fail after storage.
        Document::creating(function (): void {
            throw new RuntimeException(
                'Simulated document database failure.'
            );
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($driver)->post(
                "/api/missions/{$mission->id}/documents",
                ['file' => $file],
                ['Accept' => 'application/json']
            );

            $this->fail('The database failure should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated document database failure.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('documents', 0);

        $this->assertSame(
            [],
            Storage::disk('local')->allFiles('delivery-proofs')
        );
    }
}
