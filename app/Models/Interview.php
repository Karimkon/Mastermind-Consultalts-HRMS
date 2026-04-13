<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    protected $fillable = ['candidate_id', 'interviewer_id', 'scheduled_at', 'type', 'status', 'feedback', 'rating', 'notes'];
    public function candidate()   { return $this->belongsTo(Candidate::class); }
    public function interviewer() { return $this->belongsTo(Employee::class, 'interviewer_id'); }
}