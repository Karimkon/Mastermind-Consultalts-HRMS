<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityScoreSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date', 'scope', 'scope_key', 'label', 'score', 'open_nc', 'failed', 'quality_check_run_id',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'score' => 'decimal:2',
    ];
}
