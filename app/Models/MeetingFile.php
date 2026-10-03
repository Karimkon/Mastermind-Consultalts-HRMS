<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document attached to a meeting — the papers people are meant to have read.
 *
 * Stored on the private disk; MeetingController::download() decides who may
 * fetch one. See the migration for why.
 */
class MeetingFile extends Model
{
    protected $fillable = [
        'meeting_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** "2.4 MB", for a list that people read rather than parse. */
    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? $bytes : round($bytes, 1)) . ' ' . $units[$i];
    }
}
