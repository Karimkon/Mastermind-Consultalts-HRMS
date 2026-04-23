<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortlistingQuestion extends Model
{
    protected $fillable = [
        'criteria_id', 'question', 'question_type', 'options', 'correct_answer', 'weight', 'sort_order',
    ];

    protected $casts = ['options' => 'array'];

    public function criteria() { return $this->belongsTo(ShortlistingCriteria::class, 'criteria_id'); }

    /**
     * Calculate the score earned for a given candidate answer.
     * Returns points earned (float) and the max possible points.
     */
    public function scoreAnswer(mixed $answer): array
    {
        $weight = (float) $this->weight;

        if ($answer === null || $answer === '') {
            return ['earned' => 0, 'max' => $this->question_type !== 'text' ? $weight : 0];
        }

        return match ($this->question_type) {
            'multiple_choice' => $this->scoreMultipleChoice($answer, $weight),
            'yes_no'          => $this->scoreYesNo($answer, $weight),
            'scale'           => $this->scoreScale($answer, $weight),
            default           => ['earned' => 0, 'max' => 0], // text — manual review
        };
    }

    private function scoreMultipleChoice(mixed $answer, float $weight): array
    {
        $options = $this->options ?? [];
        $index   = (int) $answer;
        $correct = false;

        if (isset($options[$index]) && ($options[$index]['is_correct'] ?? false)) {
            $correct = true;
        }

        return ['earned' => $correct ? $weight : 0, 'max' => $weight];
    }

    private function scoreYesNo(mixed $answer, float $weight): array
    {
        $preferred = strtolower($this->correct_answer ?? 'yes');
        $given     = strtolower((string) $answer);
        return ['earned' => $given === $preferred ? $weight : 0, 'max' => $weight];
    }

    private function scoreScale(mixed $answer, float $weight): array
    {
        $value    = max(1, min(5, (int) $answer));
        $earned   = round(($value / 5) * $weight, 2);
        return ['earned' => $earned, 'max' => $weight];
    }
}
