<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityCorrectiveAction extends Model
{
    protected $fillable = [
        'quality_nonconformity_id', 'type', 'action', 'root_cause', 'owner_id',
        'status', 'due_date', 'completed_at', 'verified_by', 'verified_at', 'effectiveness_notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function nonconformity(): BelongsTo
    {
        return $this->belongsTo(QualityNonconformity::class, 'quality_nonconformity_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
