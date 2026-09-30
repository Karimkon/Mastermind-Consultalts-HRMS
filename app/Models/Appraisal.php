<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One employee's Individual Balanced Score Card for a review period.
 *
 * Mirrors the signed-off Excel: four perspectives, each KPI carrying a target,
 * an actual, a 1–5 rating and a weight, with the weighted index rolling up to
 * an overall score out of 5.
 */
class Appraisal extends Model
{
    protected $fillable = [
        'title', 'employee_id', 'type', 'client_id', 'appraisal_template_id', 'year', 'period',
        'review_from', 'review_to', 'initiated_by', 'appraiser_id', 'return_to_id',
        'status', 'rating_scale_id', 'overall_index', 'overall_percent', 'overall_band',
        'employee_comment', 'manager_comment', 'employee_signed_at', 'manager_signed_at',
        'self_assessed_at',
    ];

    protected $casts = [
        'review_from'        => 'date',
        'review_to'          => 'date',
        'overall_index'      => 'float',
        'overall_percent'    => 'float',
        'employee_signed_at' => 'datetime',
        'self_assessed_at'   => 'datetime',
        'manager_signed_at'  => 'datetime',
    ];

    /** The four perspectives, in the order the Excel prints them. */
    public const PERSPECTIVES = [
        'financial'        => 'Financials',
        'customer'         => 'Customer Perspective',
        'internal_process' => 'Internal Business Process',
        'learning_growth'  => 'Learning & Growth',
    ];

    /** The rating scale printed at the foot of the card. */
    public const BANDS = [
        1 => ['label' => 'Poor',      'range' => 'Below 50%',   'min' => 0],
        2 => ['label' => 'Fair',      'range' => '51 – 65%',    'min' => 50],
        3 => ['label' => 'Good',      'range' => '66 – 75%',    'min' => 65],
        4 => ['label' => 'Very Good', 'range' => '76 – 95%',    'min' => 75],
        5 => ['label' => 'Excellent', 'range' => 'Above 96%',   'min' => 95],
    ];

    public function employee()   { return $this->belongsTo(Employee::class); }
    public function template()   { return $this->belongsTo(AppraisalTemplate::class, 'appraisal_template_id'); }
    public function client()     { return $this->belongsTo(Client::class); }
    public function initiator()  { return $this->belongsTo(User::class, 'initiated_by'); }
    public function appraiser()  { return $this->belongsTo(User::class, 'appraiser_id'); }
    public function returnTo()   { return $this->belongsTo(User::class, 'return_to_id'); }

    public function kpis()        { return $this->hasMany(AppraisalKpi::class)->orderBy('sort_order')->orderBy('id'); }
    public function actions()     { return $this->hasMany(AppraisalAction::class)->orderBy('sort_order'); }
    public function attachments() { return $this->hasMany(AppraisalAttachment::class)->latest(); }
    public function history()     { return $this->hasMany(AppraisalHistory::class)->latest(); }

    /** Total of every KPI weight. Must never exceed 100. */
    public function totalWeight(): float
    {
        return round((float) $this->kpis()->sum('weightage'), 2);
    }

    /** Weight still unallocated — what a new KPI may take at most. */
    public function remainingWeight(): float
    {
        return round(max(0, 100 - $this->totalWeight()), 2);
    }

    public function weightIsComplete(): bool
    {
        return abs($this->totalWeight() - 100) < 0.01;
    }

    /**
     * The admin's target split for this card, or null when no template applies.
     * Supervisors are shown this beside what they have actually allocated.
     */
    public function targetWeights(): ?array
    {
        return $this->template?->perspectiveWeights();
    }

    /** KPIs grouped by perspective, in the Excel's order. */
    public function kpisByPerspective(): array
    {
        $grouped = $this->kpis->groupBy('perspective');
        $targets = $this->targetWeights() ?? [];
        $out = [];
        foreach (self::PERSPECTIVES as $key => $label) {
            $rows = $grouped->get($key, collect());
            $out[$key] = [
                'label'  => $label,
                'rows'   => $rows,
                'weight' => round((float) $rows->sum('weightage'), 2),
                'index'  => round((float) $rows->sum('weighted_index'), 4),
                'target' => $targets[$key] ?? null,
            ];
        }
        return $out;
    }

    public function ratingScale() { return $this->belongsTo(RatingScale::class, 'rating_scale_id'); }

    /**
     * The scale this card is rated on.
     *
     * Its own, the one its template carries, or the system default - in that
     * order, so an existing card keeps working whatever was or was not chosen
     * when it was created.
     */
    public function scale(): ?RatingScale
    {
        return $this->relationLoaded('ratingScale') && $this->ratingScale
            ? $this->ratingScale
            : ($this->ratingScale
                ?? $this->template?->ratingScale
                ?? RatingScale::default());
    }

    /** Top of the scale, and the divisor behind the overall percentage. */
    public function maxPoints(): int
    {
        return $this->scale()?->max_points ?: 5;
    }

    /**
     * Roll the KPI rows up into the overall score and persist it.
     *
     * The percentage is the index over full marks. Full marks used to be the
     * literal 5 written here; it is the scale's own top now, so a card rated
     * out of 10 does not silently report half the score it earned.
     */
    public function recalculate(): void
    {
        $index   = round((float) $this->kpis()->sum('weighted_index'), 3);
        $max     = $this->maxPoints();
        $percent = $max > 0 ? round($index / $max * 100, 2) : 0.0;

        $this->update([
            'overall_index'   => $index,
            'overall_percent' => $percent,
            'overall_band'    => $this->scale()?->bandFor($percent) ?? self::bandFor($percent),
        ]);
    }

    /**
     * Which band a percentage falls into, on the fixed 1-5 scale.
     *
     * Kept as a fall-back for cards written before scales existed and for the
     * moment before any scale has been seeded. A card with a scale uses that
     * scale's own bands instead.
     */
    public static function bandFor(?float $percent): ?int
    {
        if ($percent === null) return null;
        if ($percent > 95) return 5;
        if ($percent >= 76) return 4;
        if ($percent >= 66) return 3;
        if ($percent >= 51) return 2;
        return 1;
    }

    public function bandLabel(): string
    {
        if (! $this->overall_band) return '—';

        return $this->scale()?->labelFor($this->overall_band)
            ?? (self::BANDS[$this->overall_band]['label'] ?? '—');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'           => 'Setting KPIs',
            // The employee is asked what they achieved before anybody rates them.
            'self_assessment' => 'Awaiting employee self-assessment',
            'with_appraiser'  => 'With appraiser',
            'with_manager'    => 'Awaiting manager confirmation',
            'with_employee'   => 'Awaiting employee sign-off',
            'completed'       => 'Completed',
            default         => ucfirst($this->status),
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'draft'           => 'gray',
            'self_assessment' => 'yellow',
            'with_appraiser'  => 'blue',
            'with_manager'    => 'purple',
            'with_employee'   => 'yellow',
            'completed'       => 'green',
            default          => 'gray',
        };
    }

    /** The user expected to act right now. */
    public function currentHolderId(): ?int
    {
        return match ($this->status) {
            'draft'          => $this->initiated_by,
            // The card is with the employee first, before anybody rates them.
            'self_assessment' => $this->employee?->user_id,
            'with_appraiser' => $this->appraiser_id,
            'with_manager'   => $this->return_to_id ?? $this->initiated_by,
            'with_employee'  => $this->employee?->user_id,
            default          => null,
        };
    }

    public function log(string $action, ?string $to = null, ?string $comment = null): void
    {
        $this->history()->create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'from_status' => $this->getOriginal('status') ?? $this->status,
            'to_status'   => $to ?? $this->status,
            'comment'     => $comment,
        ]);
    }
}
