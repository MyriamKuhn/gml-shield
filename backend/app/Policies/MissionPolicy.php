<?php

namespace App\Policies;

use App\Enums\MissionStatus;
use App\Enums\UserRole;
use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    /**
     * ADMIN and DISPATCHER can list all missions.
     * DRIVER can list missions, but the controller must
     * restrict the query to their assigned missions.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMIN,
            UserRole::DISPATCHER,
            UserRole::DRIVER,
        ], true);
    }

    /**
     * Restrict mission access according to role and ownership.
     *
     * Administrators can supervise all missions.
     * Dispatchers can only access their own missions.
     * Drivers can only access assigned missions.
     */
    public function view(User $user, Mission $mission): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->role === UserRole::DISPATCHER) {
            return $mission->created_by === $user->id;
        }

        return $user->role === UserRole::DRIVER
            && $mission->driver_id === $user->id;
    }

    /**
     * Only managers can create missions.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMIN,
            UserRole::DISPATCHER,
        ], true);
    }

    /**
     * Only administrators or the owning dispatcher
     * can update a pending mission.
     */
    public function update(User $user, Mission $mission): bool
    {
        if ($mission->status !== MissionStatus::PENDING) {
            return false;
        }

        return $user->role === UserRole::ADMIN
            || (
                $user->role === UserRole::DISPATCHER
                && $mission->created_by === $user->id
            );
    }

    /**
     * Only managers can assign drivers to pending missions.
     */
    public function assign(User $user, Mission $mission): bool
    {
        return $this->update($user, $mission);
    }

    /**
     * Drivers can start their own assigned pending missions.
     */
    public function start(User $user, Mission $mission): bool
    {
        return $user->role === UserRole::DRIVER
            && $mission->driver_id === $user->id
            && $mission->status === MissionStatus::PENDING;
    }

    /**
     * Drivers can complete their own missions in progress.
     */
    public function complete(User $user, Mission $mission): bool
    {
        return $user->role === UserRole::DRIVER
            && $mission->driver_id === $user->id
            && $mission->status === MissionStatus::IN_PROGRESS;
    }

    /**
     * Only administrators or the owning dispatcher
     * can cancel a pending or in-progress mission.
     */
    public function cancel(User $user, Mission $mission): bool
    {
        if (
            !in_array($mission->status, [
                MissionStatus::PENDING,
                MissionStatus::IN_PROGRESS,
            ], true)
        ) {
            return false;
        }

        return $user->role === UserRole::ADMIN
            || (
                $user->role === UserRole::DISPATCHER
                && $mission->created_by === $user->id
            );
    }

    /**
     * Mission deletion is forbidden to preserve traceability.
     */
    public function delete(User $user, Mission $mission): bool
    {
        return false;
    }
}
