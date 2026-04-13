<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TrainingAssessment extends Model
{
    protected $fillable = ['course_id','employee_id','score','max_score','passed','passed_at','attempts','notes','assessed_by'];
    protected $casts    = ['passed' => 'boolean', 'passed_at' => 'datetime'];

    public function course()   { return $this->belongsTo(TrainingCourse::class, 'course_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
