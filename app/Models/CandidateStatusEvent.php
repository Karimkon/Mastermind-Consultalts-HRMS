<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateStatusEvent extends Model
{
    protected $fillable = [
        'candidate_id', 'from_status', 'to_status', 'message', 'channels', 'created_by',
    ];

    public function candidate() { return $this->belongsTo(Candidate::class); }
    public function author()    { return $this->belongsTo(User::class, 'created_by'); }

    /** The channels this event actually went out on. */
    public function getChannelListAttribute(): array
    {
        return $this->channels ? array_filter(explode(',', $this->channels)) : [];
    }
}
