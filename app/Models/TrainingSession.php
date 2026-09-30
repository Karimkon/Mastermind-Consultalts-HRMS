<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One scheduled run of a training course: the unit the annual plan is made of.
 */
class TrainingSession extends Model
{
    protected $fillable = [
        'training_course_id', 'title', 'category', 'delivery',
        'plan_year', 'starts_on', 'ends_on', 'duration_days', 'duration_hours',
        'venue', 'trainer', 'provider', 'max_participants',
        'cost_pedagogic', 'cost_logistic', 'cost_remuneration', 'cost_company',
        'status', 'initiated_by', 'hr_approved_by', 'hr_approved_at',
        'ceo_approved_by', 'ceo_approved_at', 'decision_note',
        'justification', 'notes',
    ];

    protected $casts = [
        'starts_on'         => 'date',
        'ends_on'           => 'date',
        'hr_approved_at'    => 'datetime',
        'ceo_approved_at'   => 'datetime',
        'duration_days'     => 'float',
        'duration_hours'    => 'float',
        'cost_pedagogic'    => 'float',
        'cost_logistic'     => 'float',
        'cost_remuneration' => 'float',
        'cost_company'      => 'float',
        'plan_year'         => 'integer',
    ];

    /** Where a session sits in the approval chain, and what it is called. */
    public const FLOW = [
        'draft'       => ['label' => 'Draft',            'badge' => 'slate'],
        'pending_hr'  => ['label' => 'Awaiting HR',      'badge' => 'yellow'],
        'pending_ceo' => ['label' => 'Awaiting CEO',     'badge' => 'orange'],
        'approved'    => ['label' => 'Approved',         'badge' => 'green'],
        'rejected'    => ['label' => 'Rejected',         'badge' => 'red'],
        'completed'   => ['label' => 'Completed',        'badge' => 'blue'],
        'cancelled'   => ['label' => 'Cancelled',        'badge' => 'slate'],
    ];

    public function course()      { return $this->belongsTo(TrainingCourse::class, 'training_course_id'); }
    public function initiator()   { return $this->belongsTo(User::class, 'initiated_by'); }
    public function hrApprover()  { return $this->belongsTo(User::class, 'hr_approved_by'); }
    public function ceoApprover() { return $this->belongsTo(User::class, 'ceo_approved_by'); }

    public function participants()
    {
        return $this->hasMany(TrainingSessionParticipant::class)->with('employee.department');
    }

    public function statusLabel(): string
    {
        return self::FLOW[$this->status]['label'] ?? ucfirst($this->status);
    }

    public function statusBadge(): string
    {
        return self::FLOW[$this->status]['badge'] ?? 'slate';
    }

    /** What the whole session costs, however finance splits it up. */
    public function totalCost(): float
    {
        return round($this->cost_pedagogic + $this->cost_logistic
                   + $this->cost_remuneration + $this->cost_company, 2);
    }

    /**
     * Cost per head.
     *
     * Divided by people actually nominated rather than by the seat limit: an
     * empty seat still costs, and pretending otherwise flatters the figure.
     */
    public function costPerParticipant(): ?float
    {
        $n = $this->participants()->count();
        return $n ? round($this->totalCost() / $n, 2) : null;
    }

    /** Approved means the chain finished; completed sessions stay approved. */
    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'completed'], true);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }
}
