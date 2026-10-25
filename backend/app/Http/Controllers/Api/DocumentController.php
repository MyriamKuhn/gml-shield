<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use App\Models\Document;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    /**
     * Store a delivery proof in private storage.
     *
     * Lock the mission to prevent status changes during
     * document registration. Remove the stored file if
     * the database operation fails.
     */
    public function store(
        StoreDocumentRequest $request,
        Mission $mission
    ): JsonResponse {
        $file = $request->file('file');
        $path = null;

        try {
            $document = DB::transaction(function () use ($request, $mission, $file, &$path) {
                // Lock the mission before checking current permissions.
                $lockedMission = Mission::query()
                    ->lockForUpdate()
                    ->findOrFail($mission->id);

                Gate::forUser($request->user())
                    ->authorize('uploadDocument', $lockedMission);

                // Store the validated file outside the public webroot.
                $path = $file->store('delivery-proofs', 'local');

                if ($path === false) {
                    throw new RuntimeException(
                        'Unable to store the delivery proof.'
                    );
                }

                // Create the document through its authorized mission.
                return $lockedMission->documents()->create([
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'uploaded_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            // Compensate for filesystem/database transaction mismatch.
            if ($path !== null && $path !== false) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return response()->json([
            'id' => $document->id,
            'mission_id' => $document->mission_id,
            'mime_type' => $document->mime_type,
            'uploaded_at' => $document->uploaded_at,
        ], 201);
    }

    /**
     * List delivery proofs for a mission.
     *
     * Only the dispatcher who created the mission
     * may view its document metadata.
     *
     * Private storage paths are never exposed.
     */
    public function index(Mission $mission): JsonResponse
    {
        Gate::authorize('downloadDocument', $mission);

        $documents = $mission->documents()
            ->orderByDesc('uploaded_at')
            ->get([
                'id',
                'mission_id',
                'mime_type',
                'uploaded_at',
            ]);

        return response()->json([
            'data' => $documents,
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Download a delivery proof belonging to the requested mission.
     *
     * Access is restricted to the owning dispatcher.
     * Files are retrieved exclusively from private storage.
     */
    public function download(
        Mission $mission,
        Document $document
    ): BinaryFileResponse {
        // Authorize access to the parent mission first.
        Gate::authorize('downloadDocument', $mission);

        // Prevent access to documents from another mission.
        abort_unless(
            $document->mission_id === $mission->id,
            404
        );

        // Only files directly inside delivery-proofs are accepted.
        // Reject traversal sequences, nested paths and unexpected extensions.
        $path = $document->file_path;

        abort_unless(
            is_string($path)
            && preg_match(
                '~\Adelivery-proofs/[A-Za-z0-9_-]+\.(?:pdf|jpg|jpeg|png)\z~D',
                $path
            ) === 1,
            404
        );

        $disk = Storage::disk('local');

        // Resolve the actual filesystem location, including symlinks.
        $directory = realpath(
            $disk->path('delivery-proofs')
        );

        $resolvedPath = realpath($disk->path($path));

        abort_unless(
            $directory !== false
            && $resolvedPath !== false
            && dirname($resolvedPath) === $directory
            && is_file($resolvedPath),
            404
        );

        // Derive the extension from the validated MIME type,
        // never from a client-supplied filename.
        $extension = match ($document->mime_type) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };

        abort_if($extension === null, 404);

        $downloadName = 'delivery-proof-' . $document->id . '.' . $extension;

        return response()->download(
            $resolvedPath,
            $downloadName,
            [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
