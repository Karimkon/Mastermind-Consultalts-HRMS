<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TrainingCourse extends Model
{
    protected $fillable = ['title', 'description', 'category', 'duration_hours', 'material_path'];
    public function enrollments() { return $this->hasMany(TrainingEnrollment::class, 'course_id'); }
}