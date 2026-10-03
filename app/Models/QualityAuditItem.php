<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityAuditItem extends Model
{
    protected $fillable = [
        'quality_audit_id', 'quality_standard_id', 'question', 'result', 'notes', 'evidence',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(QualityAudit::class, 'quality_audit_id');
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(QualityStandard::class, 'quality_standard_id');
    }
}
