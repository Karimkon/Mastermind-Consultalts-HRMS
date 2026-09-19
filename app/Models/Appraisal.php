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
        'status', 'overall_index', 'overall_percent', 'overall_band',
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

    /**
     * Roll the KPI rows up into the overall score and persist it.
     * Overall index is out of 5, so the percentage is index ÷ 5.
     */
    public function recalculate(): void
    {
        $index   = round((float) $this->kpis()->sum('weighted_index'), 3);
        $percent = round($index / 5 * 100, 2);

        $this->update([
            'overall_index'   => $index,
            'overall_percent' => $percent,
            'overall_band'    => self::bandFor($percent),
        ]);
    }

    /** Which 1–5 band a percentage falls into. */
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
        return $this->overall_band ? self::BANDS[$this->overall_band]['label'] : '—';
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
