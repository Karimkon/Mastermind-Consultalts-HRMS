<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortlistingCriteria extends Model
{
    protected $table    = 'shortlisting_criteria';
    protected $fillable = [
        'job_posting_id', 'title', 'description', 'top_n', 'is_active', 'created_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function jobPosting()  { return $this->belongsTo(JobPosting::class); }
    public function questions()   { return $this->hasMany(ShortlistingQuestion::class, 'criteria_id')->orderBy('sort_order'); }
    public function responses()   { return $this->hasMany(ShortlistingResponse::class, 'criteria_id'); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }

    /** Maximum achievable score for this criteria set (sum of weights of auto-scored questions). */
    public function maxScore(): float
    {
        return $this->questions
            ->where('question_type', '!=', 'text')
            ->sum('weight');
    }
}
