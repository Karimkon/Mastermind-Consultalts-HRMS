<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortlistingQuestion extends Model
{
    /** Types whose answers the system can mark on its own. */
    public const SCORED_TYPES = ['multiple_choice', 'yes_no', 'scale'];

    protected $fillable = [
        'criteria_id', 'question', 'question_type', 'options', 'correct_answer', 'weight', 'sort_order',
    ];

    protected $casts = ['options' => 'array'];

    public function criteria() { return $this->belongsTo(ShortlistingCriteria::class, 'criteria_id'); }

    /** Free text is read by a person, so it carries no marks. */
    public function isScored(): bool
    {
        return in_array($this->question_type, self::SCORED_TYPES, true);
    }

    /**
     * Calculate the score earned for a given candidate answer.
     * Returns points earned (float) and the max possible points.
     */
    public function scoreAnswer(mixed $answer): array
    {
        $weight = (float) $this->weight;

        if (! $this->isScored()) {
            return ['earned' => 0, 'max' => 0];
        }

        if ($answer === null || $answer === '') {
            return ['earned' => 0, 'max' => $weight];
        }

        return match ($this->question_type) {
            'multiple_choice' => $this->scoreMultipleChoice($answer, $weight),
            'yes_no'          => $this->scoreYesNo($answer, $weight),
            'scale'           => $this->scoreScale($answer, $weight),
            default           => ['earned' => 0, 'max' => 0],
        };
    }

    /**
     * Multiple choice, where each option can be worth a different amount.
     *
     * "How many years of experience?" is not a right-or-wrong question: 1-2
     * years is worth something, 5+ is worth more. An option may therefore
     * carry its own `marks`, up to the question's weight.
     *
     * Older questionnaires stored only `is_correct` on each option, so when
     * no option carries marks the old all-or-nothing rule still applies and
     * questionnaires written before this keep scoring exactly as they did.
     */
    private function scoreMultipleChoice(mixed $answer, float $weight): array
    {
        $options = $this->options ?? [];
        $index   = (int) $answer;
        $chosen  = $options[$index] ?? null;

        if ($chosen === null) {
            return ['earned' => 0, 'max' => $weight];
        }

        $graded = collect($options)->contains(fn ($o) => isset($o['marks']));

        if ($graded) {
            // Never pay out more than the question is worth, whatever was
            // typed into the form.
            $earned = min($weight, max(0, (float) ($chosen['marks'] ?? 0)));
            return ['earned' => round($earned, 2), 'max' => $weight];
        }

        return ['earned' => ($chosen['is_correct'] ?? false) ? $weight : 0, 'max' => $weight];
    }

    private function scoreYesNo(mixed $answer, float $weight): array
    {
        $preferred = strtolower($this->correct_answer ?? 'yes');
        $given     = strtolower((string) $answer);
        return ['earned' => $given === $preferred ? $weight : 0, 'max' => $weight];
    }

    private function scoreScale(mixed $answer, float $weight): array
    {
        $value  = max(1, min(5, (int) $answer));
        $earned = round(($value / 5) * $weight, 2);
        return ['earned' => $earned, 'max' => $weight];
    }
}
