<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    protected $fillable = ['title', 'department_id', 'type', 'description', 'requirements', 'benefits', 'status', 'deadline', 'location', 'vacancies', 'salary_min', 'salary_max'];
    public function department() { return $this->belongsTo(Department::class); }
    public function candidates() { return $this->hasMany(Candidate::class); }
}