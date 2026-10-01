<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Somebody outside the company with an account on the careers app.
 *
 * Authenticatable in its own right, on its own guard, with no Spatie roles
 * and no employee record. A job seeker can see jobs, their own applications
 * and their own documents. There is nothing on this model that any HR
 * controller reads, which is the point.
 */
class JobSeeker extends Authenticatable
{
    // Deliberately not Notifiable: this system's `notifications` table is
    // its own (a user_id, a type, a title), not Laravel's polymorphic one,
    // so the trait's relationships would query a notifiable_type column
    // that does not exist. Job seekers have job_seeker_notifications.
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'location',
        'education_level', 'experience_years', 'notify_email', 'notify_sms',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password'          => 'hashed',
        'email_verified_at' => 'datetime',
        'notify_email'      => 'boolean',
        'notify_sms'        => 'boolean',
    ];

    public function categories()   { return $this->belongsToMany(JobCategory::class); }
    public function applications() { return $this->hasMany(Candidate::class); }

    /** Has this seeker already applied for this posting? */
    public function hasAppliedTo(int $jobPostingId): bool
    {
        return $this->applications()->where('job_posting_id', $jobPostingId)->exists();
    }
}
