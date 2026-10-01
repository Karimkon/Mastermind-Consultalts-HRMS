<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortlistingCriteria extends Model
{
    /**
     * What a screening questionnaire is worth, unless one says otherwise.
     *
     * The business rule: every assessment in the recruitment process totals
     * 30 marks, shared out across the questions by the recruiter.
     */
    public const DEFAULT_TOTAL_MARKS = 30;

    protected $table    = 'shortlisting_criteria';
    protected $fillable = [
        'job_posting_id', 'title', 'description', 'top_n', 'total_marks', 'is_active', 'created_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function jobPosting()  { return $this->belongsTo(JobPosting::class); }
    public function questions()   { return $this->hasMany(ShortlistingQuestion::class, 'criteria_id')->orderBy('sort_order'); }
    public function responses()   { return $this->hasMany(ShortlistingResponse::class, 'criteria_id'); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * Maximum achievable score: the sum of the weights of the questions the
     * system marks itself.
     *
     * Read off the questions rather than off total_marks on purpose. They
     * agree for any questionnaire saved since the total was enforced; for
     * older ones whose weights never added to anything in particular, this
     * keeps the percentage honest instead of dividing by a number the
     * questions were never built against.
     */
    public function maxScore(): float
    {
        return (float) $this->questions
            ->filter(fn ($q) => $q->isScored())
            ->sum('weight');
    }

    /** How many of the allotted marks the recruiter has handed out so far. */
    public function allocatedMarks(): int
    {
        return (int) $this->questions
            ->filter(fn ($q) => $q->isScored())
            ->sum('weight');
    }

    /** Does this questionnaire add up to what it is supposed to be worth? */
    public function isBalanced(): bool
    {
        return $this->allocatedMarks() === (int) $this->total_marks;
    }
}
