<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

/**
 * Only an active driver may subscribe to their own mission channel.
 *
 * Never authorize a subscription based solely on a client-supplied ID.
 */
Broadcast::channel('drivers.{driverId}', function (User $user, int $driverId): bool {
    return $user->is_active
        && $user->role === UserRole::DRIVER
        && $user->id === $driverId;
});
