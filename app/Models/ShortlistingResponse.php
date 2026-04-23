<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortlistingResponse extends Model
{
    protected $fillable = [
        'candidate_id', 'criteria_id', 'answers', 'total_score', 'max_score', 'percentage',
    ];

    protected $casts = ['answers' => 'array'];

    public function candidate() { return $this->belongsTo(Candidate::class); }
    public function criteria()  { return $this->belongsTo(ShortlistingCriteria::class, 'criteria_id'); }

    /**
     * Calculate and persist scores from the stored answers.
     */
    public function recalculate(): void
    {
        $criteria = $this->criteria()->with('questions')->first();
        $totalScore = 0;
        $maxScore   = 0;

        foreach ($criteria->questions as $question) {
            $answer   = $this->answers[$question->id] ?? null;
            $result   = $question->scoreAnswer($answer);
            $totalScore += $result['earned'];
            $maxScore   += $result['max'];
        }

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        $this->update([
            'total_score' => $totalScore,
            'max_score'   => $maxScore,
            'percentage'  => $percentage,
        ]);
    }
}
