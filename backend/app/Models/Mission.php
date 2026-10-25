<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\MissionStatus;

class Mission extends Model
{
    /**
     * Attributes allowed for mass assignment.
     *
     * Sensitive fields such as status, driver_id and created_by
     * are intentionally excluded.
     */
    protected $fillable = [
        'reference',
        'title',
        'description',
        'pickup_address',
        'delivery_address',
        'scheduled_at',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'status' => MissionStatus::class,
        ];
    }

    /**
     * Driver assigned to the mission.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * User who created the mission.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
