<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Part II — a problem area, what was agreed about it, and by when. */
class AppraisalAction extends Model
{
    protected $fillable = ['appraisal_id', 'problem_area', 'remedial_action', 'by_when', 'sort_order'];
    protected $casts    = ['by_when' => 'date'];

    public function appraisal() { return $this->belongsTo(Appraisal::class); }
}
