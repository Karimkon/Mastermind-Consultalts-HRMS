<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A reusable KPI row belonging to an appraisal template. */
class AppraisalTemplateKpi extends Model
{
    protected $fillable = [
        'appraisal_template_id', 'perspective', 'kra_name',
        'performance_measure', 'target', 'weightage', 'evidence_note', 'sort_order',
    ];

    protected $casts = ['weightage' => 'float'];

    public function template() { return $this->belongsTo(AppraisalTemplate::class, 'appraisal_template_id'); }

    public function perspectiveLabel(): string
    {
        return Appraisal::PERSPECTIVES[$this->perspective] ?? ucfirst($this->perspective);
    }
}
