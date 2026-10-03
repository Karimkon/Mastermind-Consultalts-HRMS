<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityAudit extends Model
{
    protected $fillable = [
        'reference', 'title', 'scope', 'type', 'status', 'auditor_id', 'department_id',
        'planned_date', 'started_at', 'completed_at', 'score', 'summary',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(QualityAuditItem::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public static function nextReference(): string
    {
        $n = (int) static::max('id') + 1;
        return 'QA-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
