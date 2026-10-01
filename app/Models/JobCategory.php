<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function jobPostings() { return $this->hasMany(JobPosting::class); }
    public function jobSeekers()  { return $this->belongsToMany(JobSeeker::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
