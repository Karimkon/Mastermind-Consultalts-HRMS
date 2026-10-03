<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityGoalFile extends Model
{
    protected $fillable = ['quality_goal_id', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    public function goal(): BelongsTo { return $this->belongsTo(QualityGoal::class, 'quality_goal_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
