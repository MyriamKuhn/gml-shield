<?php

namespace App\Enums;

/**
 * Allowed lifecycle statuses for a transport mission.
 */
enum MissionStatus: string
{
    case PENDING = 'PENDING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
