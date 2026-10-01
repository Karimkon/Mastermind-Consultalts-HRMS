<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerNotification extends Model
{
    protected $fillable = [
        'job_seeker_id', 'type', 'title', 'body', 'data', 'action_url', 'read_at',
    ];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public function jobSeeker() { return $this->belongsTo(JobSeeker::class); }

    public function scopeUnread($q) { return $q->whereNull('read_at'); }
}
