<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = ['job_posting_id', 'name', 'email', 'phone', 'resume_path', 'score', 'status', 'notes'];
    public function jobPosting() { return $this->belongsTo(JobPosting::class); }
    public function interviews() { return $this->hasMany(Interview::class); }
}