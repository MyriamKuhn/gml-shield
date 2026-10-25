<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    /**
     * Attributes allowed for mass assignment.
     *
     * The mission relationship is assigned explicitly
     * by the backend after authorization.
     */
    protected $fillable = [
        'file_path',
        'mime_type',
        'uploaded_at',
    ];

    /**
     * Cast the upload timestamp to a date object.
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the mission associated with this delivery proof.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}
