<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = [
        'job_posting_id', 'first_name', 'last_name', 'email', 'phone',
        'resume_path', 'cover_letter', 'score', 'score_breakdown',
        'status', 'notes', 'source', 'experience_years', 'education_level',
        'client_shortlist_status', 'client_shortlisted_by', 'client_shortlist_notes', 'client_actioned_at',
        'offer_amount', 'offer_date', 'offer_expiry', 'offer_letter_path',
    ];

    protected $casts = ['client_actioned_at' => 'datetime'];

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function jobPosting()        { return $this->belongsTo(JobPosting::class); }
    public function interviews()        { return $this->hasMany(Interview::class); }
    public function clientShortlister() { return $this->belongsTo(Client::class, 'client_shortlisted_by'); }
}