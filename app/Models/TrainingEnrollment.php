<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TrainingEnrollment extends Model
{
    protected $fillable = ['employee_id', 'course_id', 'status', 'progress_pct', 'completed_at'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function course()   { return $this->belongsTo(TrainingCourse::class, 'course_id'); }
}