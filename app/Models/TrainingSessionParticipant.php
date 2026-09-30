<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One person on one training session, and what the training did for them.
 */
class TrainingSessionParticipant extends Model
{
    protected $fillable = [
        'training_session_id', 'employee_id', 'recommended_by', 'appraisal_id',
        'reason', 'attendance', 'completed_on', 'score',
        'roi_metric', 'roi_unit', 'roi_before', 'roi_after',
        'roi_lower_is_better', 'roi_measured_on', 'roi_note',
    ];

    protected $casts = [
        'completed_on'        => 'date',
        'roi_measured_on'     => 'date',
        'score'               => 'float',
        'roi_before'          => 'float',
        'roi_after'           => 'float',
        'roi_lower_is_better' => 'boolean',
    ];

    public function session()     { return $this->belongsTo(TrainingSession::class, 'training_session_id'); }
    public function employee()    { return $this->belongsTo(Employee::class); }
    public function recommender() { return $this->belongsTo(User::class, 'recommended_by'); }
    public function appraisal()   { return $this->belongsTo(Appraisal::class); }

    /**
     * Improvement as a percentage, or null until both readings exist.
     *
     * Direction matters: 120 minutes falling to 30 is a 75% gain, while 30 units
     * an hour rising to 40 is a 33% gain. The same arithmetic on the wrong
     * assumption reports an improvement as a decline.
     */
    public function improvementPercent(): ?float
    {
        if ($this->roi_before === null || $this->roi_after === null) return null;
        if ((float) $this->roi_before == 0.0) return null;

        $delta = $this->roi_lower_is_better
            ? $this->roi_before - $this->roi_after     // time saved
            : $this->roi_after - $this->roi_before;    // output gained

        return round($delta / abs($this->roi_before) * 100, 1);
    }

    /** True when the measurement shows things got worse. */
    public function regressed(): bool
    {
        $p = $this->improvementPercent();
        return $p !== null && $p < 0;
    }

    public function hasMeasure(): bool
    {
        return $this->roi_before !== null && $this->roi_after !== null;
    }
}
