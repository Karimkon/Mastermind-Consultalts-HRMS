<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\ShortlistingResponse;

class Candidate extends Model
{
    protected $fillable = [
        'job_posting_id', 'job_seeker_id', 'first_name', 'last_name', 'email', 'phone',
        'resume_path', 'cover_letter', 'score', 'score_breakdown',
        'status', 'notes', 'source', 'experience_years', 'education_level',
        'client_shortlist_status', 'client_shortlisted_by', 'client_shortlist_notes', 'client_actioned_at',
        'offer_amount', 'offer_date', 'offer_expiry', 'offer_letter_path',
        'ip_address', 'country', 'region', 'city', 'origin_resolved_at',
        'tracking_code', 'notified_status',
    ];

    protected $casts = [
        'client_actioned_at' => 'datetime',
        'origin_resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Every application gets a code the moment it exists. It is the only
        // thing standing between a stranger and somebody else's application
        // status, so it is random rather than derived from the id.
        static::creating(function (Candidate $candidate) {
            if (! $candidate->tracking_code) {
                $candidate->tracking_code = static::newTrackingCode();
            }
        });
    }

    public static function newTrackingCode(): string
    {
        do {
            $code = Str::upper(Str::random(12));
        } while (static::where('tracking_code', $code)->exists());

        return $code;
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /** Where the application came from, as a person would read it. */
    public function getOriginLabelAttribute(): string
    {
        $parts = array_filter([$this->city, $this->region, $this->country]);
        return $parts ? implode(', ', array_unique($parts)) : 'Unknown';
    }

    public function jobPosting()           { return $this->belongsTo(JobPosting::class); }
    public function jobSeeker()            { return $this->belongsTo(JobSeeker::class); }
    public function interviews()           { return $this->hasMany(Interview::class); }
    public function clientShortlister()    { return $this->belongsTo(Client::class, 'client_shortlisted_by'); }
    public function shortlistingResponse() { return $this->hasOne(ShortlistingResponse::class); }
    public function documents()            { return $this->hasMany(CandidateDocument::class); }

    public function statusEvents()
    {
        return $this->hasMany(CandidateStatusEvent::class)->orderBy('created_at');
    }
}
