<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\StoreMissionRequest;
use App\Http\Requests\AssignMissionRequest;
use Illuminate\Support\Facades\DB;
use App\Enums\MissionStatus;
use App\Http\Requests\UpdateMissionRequest;
use App\Services\SecretCodeEncryptionService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use App\Jobs\RecordAuditLog;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;

class MissionController extends Controller
{
    /**
     * List missions according to the authenticated user's role.
     */
    public function index(Request $request): JsonResponse
    {
        // Verify that the user is authorized to access mission lists.
        Gate::authorize('viewAny', Mission::class);

        $user = $request->user();

        // Start with a query rather than loading all missions.
        $query = Mission::query()
            ->with([
                'driver:id,name,email',
                'creator:id,name,email',
            ])
            ->withExists([
                'documents as has_delivery_proof',
            ]);

        // Drivers must only access their assigned missions.
        if ($user->role === UserRole::DRIVER) {
            $query->where('driver_id', $user->id);
        }

        // Dispatchers only see missions they created.
        if ($user->role === UserRole::DISPATCHER) {
            $query->where('created_by', $user->id);
        }

        // Return the most recently scheduled missions first.
        $missions = $query
            ->orderByDesc('scheduled_at')
            ->paginate(15);

        return response()->json($missions);
    }

    /**
     * Display a mission only if the authenticated user
     * is authorized to view it.
     */
    public function show(Mission $mission): JsonResponse
    {
        Gate::authorize('view', $mission);

        $mission->load([
            'driver:id,name,email',
            'creator:id,name,email',
        ]);

        return response()->json($mission);
    }

    /**
     * Reveal the warehouse access code only to the assigned driver.
     *
     * Never expose cryptographic errors or encrypted values.
     */
    public function revealSecret(
        Mission $mission,
        SecretCodeEncryptionService $encryptionService
    ): JsonResponse {
        try {
            Gate::authorize('revealSecret', $mission);
        } catch (AuthorizationException $exception) {
            // Record denied attempts without exposing the mission secret.
            try {
                RecordAuditLog::dispatch(
                    userId: request()->user()?->id,
                    action: 'SECRET_CODE_REVEAL_DENIED',
                    ipAddress: request()->ip(),
                    occurredAt: now()->toDateTimeString(),
                );
            } catch (Throwable $auditException) {
                // An audit failure must never grant access.
                // Do not log credentials, secrets or request contents.
                Log::error('Denied secret access audit dispatch failed.', [
                    'mission_id' => $mission->id,
                    'user_id' => request()->user()?->id,
                    'exception_type' => get_class($auditException),
                ]);
            }

            // Preserve the original authorization decision and HTTP status.
            throw $exception;
        }

        if ($mission->secret_code === null) {
            return response()->json([
                'message' => 'No secret code is available for this mission.',
            ], 404)->header('Cache-Control', 'no-store');
        }

        try {
            $secretCode = $encryptionService->decrypt(
                $mission->secret_code
            );
        } catch (DecryptException $exception) {
            // Log only the affected mission identifier.
            // Never log the secret or encrypted payload.
            Log::error('Mission secret decryption failed.', [
                'mission_id' => $mission->id,
            ]);

            return response()->json([
                'message' => 'Unable to retrieve the secret code.',
            ], 500)->header('Cache-Control', 'no-store');
        }

        try {
            // Audit the revelation before returning the sensitive value.
            RecordAuditLog::dispatch(
                userId: request()->user()->id,
                action: 'SECRET_CODE_REVEALED',
                ipAddress: request()->ip(),
                occurredAt: now()->toDateTimeString(),
            );
        } catch (Throwable $exception) {
            // Report the infrastructure failure without logging the secret.
            Log::error('Secret revelation audit dispatch failed.', [
                'mission_id' => $mission->id,
                'user_id' => request()->user()->id,
                'exception_type' => get_class($exception),
            ]);

            // Deny access when the audit event cannot be queued.
            return response()->json([
                'message' => 'Unable to process this sensitive operation.',
            ], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'secret_code' => $secretCode,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Create a mission and encrypt its warehouse access code
     * before persisting any sensitive value.
     */
    public function store(
        StoreMissionRequest $request,
        SecretCodeEncryptionService $encryptionService
    ): JsonResponse {
        $validated = $request->validated();

        // Encrypt the secret before constructing the model.
        // Never store the submitted plaintext.
        if (array_key_exists('secret_code', $validated)) {
            $validated['secret_code'] =
                $encryptionService->encrypt($validated['secret_code']);
        }

        // Prevent the encrypted field from becoming mass assignable.
        $encryptedSecret = $validated['secret_code'] ?? null;
        unset($validated['secret_code']);

        $mission = new Mission($validated);

        // The creator is always determined by the authenticated session.
        $mission->created_by = $request->user()->id;

        // Only the encrypted representation is persisted.
        $mission->secret_code = $encryptedSecret;

        $mission->save();
        $mission->refresh();

        $mission->load([
            'driver:id,name,email',
            'creator:id,name,email',
        ]);

        return response()->json($mission, 201);
    }

    /**
     * Update the editable fields of a pending mission.
     *
     * Authorization is checked again while holding a database lock.
     */
    public function update(
        UpdateMissionRequest $request,
        Mission $mission
    ): JsonResponse {
        $validated = $request->validated();

        $updatedMission = DB::transaction(function () use ($request, $mission, $validated) {
            // Lock and reload the latest mission state.
            $lockedMission = Mission::query()
                ->lockForUpdate()
                ->findOrFail($mission->id);

            // Recheck permissions against the current database status.
            Gate::forUser($request->user())
                ->authorize('update', $lockedMission);

            // Only editable and validated fields can be updated.
            $lockedMission->fill($validated);
            $lockedMission->save();

            return $lockedMission;
        });

        $updatedMission->load([
            'driver:id,name,email',
            'creator:id,name,email',
        ]);

        return response()->json($updatedMission);
    }

    /**
     * Assign a driver to a pending mission.
     *
     * The mission is locked and authorization is checked again
     * to prevent concurrent status changes.
     */
    public function assign(
        AssignMissionRequest $request,
        Mission $mission
    ): JsonResponse {
        $validated = $request->validated();

        $updatedMission = DB::transaction(function () use ($request, $mission, $validated) {
            // Lock the mission row until the transaction finishes.
            $lockedMission = Mission::query()
                ->lockForUpdate()
                ->findOrFail($mission->id);

            // Recheck permissions using the current database status.
            Gate::forUser($request->user())
                ->authorize('assign', $lockedMission);

            // The Form Request has verified that this user is a DRIVER.
            $lockedMission->driver_id = $validated['driver_id'];
            $lockedMission->save();

            return $lockedMission;
        });

        $updatedMission->load([
            'driver:id,name,email',
            'creator:id,name,email',
        ]);

        return response()->json($updatedMission);
    }

    /**
     * Update the mission status through the Studi API contract.
     *
     * Reuse the existing authorized lifecycle operations
     * to preserve locking and transition rules.
     */
    public function updateStatus(
        Request $request,
        Mission $mission
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:IN_PROGRESS,COMPLETED',
            ],
        ]);

        return match ($validated['status']) {
            MissionStatus::IN_PROGRESS->value =>
                $this->start($request, $mission),

            MissionStatus::COMPLETED->value =>
                $this->complete($request, $mission),
        };
    }

    /**
     * Start a pending mission.
     *
     * Only the assigned driver can start the mission.
     * Authorization is checked while holding a database lock.
     */
    public function start(Request $request, Mission $mission): JsonResponse
    {
        $updatedMission = DB::transaction(function () use ($request, $mission) {
            // Lock and reload the current mission state.
            $lockedMission = Mission::query()
                ->lockForUpdate()
                ->findOrFail($mission->id);

            // Verify the driver and the current mission status.
            Gate::forUser($request->user())
                ->authorize('start', $lockedMission);

            // Apply the authorized status transition.
            $lockedMission->status = MissionStatus::IN_PROGRESS;
            $lockedMission->save();

            return $lockedMission;
        });

        $updatedMission->load([
            'driver:id,name,email',
            'creator:id,name,email',
        ]);

        return response()->json($updatedMission);
    }

    /**
     * Complete an in-progress mission.
     *
     * Only the assigned driver can complete the mission.
     * Authorization is checked while holding a database lock.
     */
    public function complete(Request $request, Mission $mission): JsonResponse
    {
        $updatedMission = DB::transaction(function () use ($request, $mission) {
            // Lock and reload the latest mission state.
            $lockedMission = Mission::query()
                ->lockForUpdate()
                ->findOrFail($mission->id);

            // Verify the driver and the current mission status.
            Gate::forUser($request->user())
                ->authorize('complete', $lockedMission);

            // Apply the authorized transition.
            $lockedMission->status = MissionStatus::COMPLETED;
            $lockedMission->save();

            return $lockedMission;
        });

        $updatedMission->load([
            'driver:id,name,email',
            'creator:id,name,email',
        ]);

        return response()->json($updatedMission);
    }

    /**
     * Cancel a pending or in-progress mission.
     *
     * Only ADMIN and DISPATCHER can cancel missions.
     * Authorization is rechecked while holding a database lock.
     */
    public function cancel(Request $request, Mission $mission): JsonResponse
    {
        $updatedMission = DB::transaction(function () use ($request, $mission) {
            // Lock and reload the latest mission state.
            $lockedMission = Mission::query()
                ->lockForUpdate()
                ->findOrFail($mission->id);

            // Verify the manager's permissions and current mission status.
            Gate::forUser($request->user())
                ->authorize('cancel', $lockedMission);

            // Apply the authorized transition.
            $lockedMission->status = MissionStatus::CANCELLED;
            $lockedMission->save();

            return $lockedMission;
        });

        $updatedMission->load([
            'driver:id,name,email',
            'creator:id,name,email',
        ]);

        return response()->json($updatedMission);
    }
}
