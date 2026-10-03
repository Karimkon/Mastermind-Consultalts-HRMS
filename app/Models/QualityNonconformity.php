<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QualityNonconformity extends Model
{
    protected $table = 'quality_nonconformities';

    protected $fillable = [
        'reference', 'quality_check_id', 'quality_check_result_id', 'quality_audit_id',
        'subject_type', 'subject_id', 'subject_label', 'hr_function', 'title', 'description',
        'severity', 'source', 'status', 'raised_by', 'assigned_to', 'due_date',
        'detected_at', 'resolved_at', 'resolution_notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const OPEN_STATES = ['open', 'investigating'];
    /** Worst first. */
    public const SEVERITY_ORDER = ['critical', 'high', 'medium', 'low'];

    /**
     * Order by severity, worst first, in SQL every engine understands.
     *
     * This was three copies of orderByRaw("FIELD(severity,...)") - the quality
     * dashboard, the non-conformity register and the compliance screen. FIELD()
     * is MySQL's alone, so all three threw "no such function: FIELD" the moment
     * anything tried to exercise them on SQLite, and no test could open them.
     * A CASE sorts identically and runs anywhere.
     */
    public function scopeOrderBySeverity(Builder $query): Builder
    {
        $case = 'CASE severity';
        foreach (self::SEVERITY_ORDER as $i => $severity) {
            $case .= " WHEN ? THEN {$i}";
        }
        $case .= ' ELSE ' . count(self::SEVERITY_ORDER) . ' END';

        return $query->orderByRaw($case, self::SEVERITY_ORDER);
    }


    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(QualityCheck::class, 'quality_check_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(QualityCorrectiveAction::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATES, true);
    }

    /** Next reference in the NC-000123 series. */
    public static function nextReference(): string
    {
        $n = (int) static::max('id') + 1;
        return 'NC-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
