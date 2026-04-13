<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MeetingParticipant extends Model
{
    protected $fillable = ['meeting_id', 'employee_id', 'rsvp'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function meeting()  { return $this->belongsTo(Meeting::class); }
}