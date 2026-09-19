<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the scorecard: a key result area with its measure, target,
 * actual, 1–5 rating and weight.
 */
class AppraisalKpi extends Model
{
    protected $fillable = [
        'appraisal_id', 'perspective', 'kra_name', 'performance_measure',
        'target', 'actual_achieved', 'target_percent', 'rating',
        // The employee's own view, kept apart from the appraiser's `rating`.
        // Two people rate the same KPI and will not always agree; that
        // disagreement is the conversation, and one shared column would destroy
        // whichever was written second.
        'self_rating', 'self_note',
        'weightage', 'weighted_index', 'evidence_note', 'sort_order',
    ];

    protected $casts = [
        'target_percent' => 'float',
        'weightage'      => 'float',
        'weighted_index' => 'float',
        'rating'         => 'integer',
    ];

    public function appraisal()  { return $this->belongsTo(Appraisal::class); }
    public function attachments(){ return $this->hasMany(AppraisalAttachment::class); }

    /**
     * Weighted index = rating × weight%, exactly as the Excel computes it
     * (a rating of 5 on a 15% weight gives 0.75).
     *
     * Percent achieved is derived when both target and actual read as numbers;
     * they are stored as text because the sheet mixes "100%", "3" and "85%".
     */
    public function recalculate(): void
    {
        $this->weighted_index = $this->rating !== null
            ? round($this->rating * ($this->weightage / 100), 4)
            : null;

        $target = $this->numeric($this->target);
        $actual = $this->numeric($this->actual_achieved);

        if ($target !== null && $actual !== null && $target != 0.0) {
            $this->target_percent = round($actual / $target * 100, 2);
        }

        $this->save();
    }

    /** Pull a number out of "100%", "UGX 3,000" or "3". */
    private function numeric(?string $raw): ?float
    {
        if ($raw === null || trim($raw) === '') return null;
        $clean = preg_replace('/[^0-9.\-]/', '', $raw);
        return ($clean === '' || $clean === '-') ? null : (float) $clean;
    }

    public function perspectiveLabel(): string
    {
        return Appraisal::PERSPECTIVES[$this->perspective] ?? ucfirst($this->perspective);
    }
}
