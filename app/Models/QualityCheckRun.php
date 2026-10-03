<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityCheckRun extends Model
{
    protected $fillable = [
        'triggered_by', 'trigger', 'scope', 'status', 'checks_run', 'subjects_scanned',
        'passed', 'failed', 'warnings', 'nonconformities_raised', 'score', 'summary',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'score' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(QualityCheckResult::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
