<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = [
        'title', 'organizer_id', 'start_at', 'end_at', 'location', 'meeting_url',
        'description', 'status', 'type', 'agenda', 'minutes',
        'recurrence', 'recurrence_end_date', 'parent_meeting_id',
    ];
    protected $casts = [
        'start_at'             => 'datetime',
        'end_at'               => 'datetime',
        'recurrence_end_date'  => 'date',
    ];

    public function organizer()    { return $this->belongsTo(Employee::class, 'organizer_id'); }
    public function participants() { return $this->hasMany(MeetingParticipant::class); }
    public function parent()       { return $this->belongsTo(Meeting::class, 'parent_meeting_id'); }
    public function instances()    { return $this->hasMany(Meeting::class, 'parent_meeting_id'); }

    public function isRecurring(): bool { return !empty($this->recurrence); }
}