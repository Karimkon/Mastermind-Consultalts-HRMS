<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\ShortlistingCriteria;

class JobPosting extends Model
{
    protected $fillable = [
        'title','slug','department_id','designation_id','employment_type','location',
        'description','requirements','benefits','status','is_public',
        'deadline','vacancies','salary_min','salary_max','reference_number','created_by',
    ];

    protected $casts = ['is_public' => 'boolean', 'deadline' => 'date'];

    protected static function booted(): void
    {
        static::saving(function ($job) {
            if (empty($job->slug)) {
                $base = Str::slug($job->title);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->where('id', '!=', $job->id ?? 0)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $job->slug = $slug;
            }
        });
    }

    public function department()           { return $this->belongsTo(Department::class); }
    public function candidates()           { return $this->hasMany(Candidate::class); }
    public function shortlistingCriteria() { return $this->hasMany(ShortlistingCriteria::class); }
    public function activeCriteria()       { return $this->hasOne(ShortlistingCriteria::class)->where('is_active', true)->latest(); }
}
