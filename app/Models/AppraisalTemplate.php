<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin-defined appraisal shape: the target weight for each perspective,
 * plus a starting set of KPIs a supervisor can apply in one click.
 */
class AppraisalTemplate extends Model
{
    protected $fillable = [
        'name', 'description', 'job_title',
        'financial_weight', 'customer_weight', 'internal_process_weight', 'learning_growth_weight',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'financial_weight'        => 'float',
        'customer_weight'         => 'float',
        'internal_process_weight' => 'float',
        'learning_growth_weight'  => 'float',
        'is_active'               => 'boolean',
    ];

    public function kpis()    { return $this->hasMany(AppraisalTemplateKpi::class)->orderBy('sort_order')->orderBy('id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    /** Target weight per perspective, keyed as the KPI rows are. */
    public function perspectiveWeights(): array
    {
        return [
            'financial'        => $this->financial_weight,
            'customer'         => $this->customer_weight,
            'internal_process' => $this->internal_process_weight,
            'learning_growth'  => $this->learning_growth_weight,
        ];
    }

    public function totalWeight(): float
    {
        return round(array_sum($this->perspectiveWeights()), 2);
    }

    public function weightIsComplete(): bool
    {
        return abs($this->totalWeight() - 100) < 0.01;
    }

    /** Total weight of the KPIs actually defined on the template. */
    public function kpiWeight(): float
    {
        return round((float) $this->kpis()->sum('weightage'), 2);
    }
}
