<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class QualityGoal extends Model
{
    protected $fillable = [
        'reference', 'title', 'description', 'initiator_id', 'assignee_id',
        'start_date', 'end_date', 'status', 'completed_at', 'progress_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function initiator(): BelongsTo { return $this->belongsTo(User::class, 'initiator_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function files(): HasMany { return $this->hasMany(QualityGoalFile::class); }

    /** Overdue = past its end date and not yet completed. */
    public function isOverdue(): bool
    {
        return $this->end_date
            && $this->status !== 'completed'
            && $this->status !== 'cancelled'
            && $this->end_date->isPast();
    }

    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public static function nextReference(): string
    {
        return 'QG-' . str_pad((string) ((int) static::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
