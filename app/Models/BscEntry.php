<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BscEntry extends Model
{
    protected $fillable = [
        'kra_id', 'employee_id', 'appraiser_id', 'appraiser_role',
        'actual_achieved', 'target_percent', 'rating', 'weighted_index',
        'employee_comment', 'appraiser_comment',
        'problem_areas', 'remedial_actions', 'remedial_by_when',
        'status', 'submitted_at', 'approved_at',
    ];

    protected $casts = [
        'actual_achieved'  => 'float',
        'target_percent'   => 'float',
        'weighted_index'   => 'float',
        'rating'           => 'integer',
        'remedial_by_when' => 'date',
        'submitted_at'     => 'datetime',
        'approved_at'      => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function kra()
    {
        return $this->belongsTo(BscKra::class, 'kra_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function appraiser()
    {
        return $this->belongsTo(Employee::class, 'appraiser_id');
    }

    // ─── Methods ─────────────────────────────────────────────────────────────

    /**
     * Recalculate and persist weighted_index = rating * (kra.weightage / 100).
     * Also updates target_percent if actual_achieved and kra->target are set.
     */
    public function calculateWeightedIndex(): void
    {
        $kra = $this->kra;

        if ($this->rating !== null && $kra) {
            $this->weighted_index = round($this->rating * ($kra->weightage / 100), 4);
        }

        if ($this->actual_achieved !== null && $kra && $kra->target > 0) {
            $this->target_percent = round(($this->actual_achieved / $kra->target) * 100, 2);
        }

        $this->save();
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'submitted' => '<span class="badge-blue">Submitted</span>',
            'reviewed'  => '<span class="badge-purple">Reviewed</span>',
            'approved'  => '<span class="badge-green">Approved</span>',
            default     => '<span class="badge-yellow">Draft</span>',
        };
    }
}
