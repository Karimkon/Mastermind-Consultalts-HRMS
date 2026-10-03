<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QualityCheckResult extends Model
{
    protected $fillable = [
        'quality_check_run_id', 'quality_check_id', 'status',
        'subject_type', 'subject_id', 'subject_label', 'message', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(QualityCheck::class, 'quality_check_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(QualityCheckRun::class, 'quality_check_run_id');
    }
}
