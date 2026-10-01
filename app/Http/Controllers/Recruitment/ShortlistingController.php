<?php
namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\{JobPosting, ShortlistingCriteria, ShortlistingQuestion, ShortlistingResponse, Candidate};
use App\Services\RecruitmentPipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShortlistingController extends Controller
{
    /**
     * Write one question, carrying the marks each answer is worth.
     *
     * "How many years of experience?" is not right-or-wrong: 1-2 years earns
     * something, 5+ earns more. Each option therefore stores its own `marks`.
     * `is_correct` is still written so anything reading the old shape - and a
     * questionnaire saved before this - keeps working.
     */
    private function writeQuestion(ShortlistingCriteria $criteria, array $q, int $sortOrder): void
    {
        $scored  = in_array($q['question_type'], ShortlistingQuestion::SCORED_TYPES, true);
        $weight  = $scored ? (int) $q['weight'] : 0;
        $options = null;

        if ($q['question_type'] === 'multiple_choice' && ! empty($q['options'])) {
            $correctIdx = (int) ($q['correct_answer'] ?? 0);

            $options = collect($q['options'])->map(function ($opt, $idx) use ($correctIdx, $weight) {
                $marks = isset($opt['marks']) && $opt['marks'] !== ''
                    ? min($weight, max(0, (float) $opt['marks']))
                    : ($idx === $correctIdx ? $weight : 0);

                return [
                    'text'       => $opt['text'],
                    'marks'      => round($marks, 2),
                    // Kept for the older all-or-nothing readers.
                    'is_correct' => $marks >= $weight && $weight > 0,
                ];
            })->values()->all();
        }

        ShortlistingQuestion::create([
            'criteria_id'    => $criteria->id,
            'question'       => $q['question'],
            'question_type'  => $q['question_type'],
            'options'        => $options,
            'correct_answer' => $q['question_type'] === 'multiple_choice'
                ? null
                : ($q['correct_answer'] ?? null),
            'weight'         => $weight,
            'sort_order'     => $sortOrder,
        ]);
    }

    /**
     * The rules for a questionnaire, including the one the brief is built on:
     * the marks must add up to the total, which is 30.
     *
     * Nothing enforced a total before. Weights ran 1 to 10 across up to
     * fifteen questions, so one job could be scored out of 12 and the next
     * out of 140 - and the two percentages sat side by side on the same
     * shortlist as though they meant the same thing.
     *
     * Free text is not marked by the system, so it carries no weight and is
     * left out of the total rather than quietly costing an applicant marks
     * nobody can award.
     */
    private function validateCriteria(Request $request): array
    {
        $total = (int) $request->input('total_marks', ShortlistingCriteria::DEFAULT_TOTAL_MARKS);

        $data = $request->validate([
            'title'                        => 'required|string|max:255',
            'description'                  => 'nullable|string|max:1000',
            'top_n'                        => 'required|integer|min:1|max:1000',
            'total_marks'                  => 'nullable|integer|min:1|max:100',
            'is_active'                    => 'nullable|boolean',
            'questions'                    => 'required|array|min:1|max:15',
            'questions.*.question'         => 'required|string|max:500',
            'questions.*.question_type'    => 'required|in:multiple_choice,yes_no,scale,text',
            'questions.*.weight'           => 'required|integer|min:0|max:' . $total,
            'questions.*.options'          => 'nullable|array|max:6',
            'questions.*.options.*.text'   => 'required_with:questions.*.options|string|max:255',
            'questions.*.options.*.marks'  => 'nullable|numeric|min:0|max:' . $total,
            'questions.*.correct_answer'   => 'nullable|string|max:50',
        ], [
            'questions.*.weight.max' => 'A single question cannot be worth more than the ' . $total . ' marks available.',
        ]);

        $data['total_marks'] = $total;

        $allocated = collect($data['questions'])
            ->filter(fn ($q) => in_array($q['question_type'], ShortlistingQuestion::SCORED_TYPES, true))
            ->sum(fn ($q) => (int) $q['weight']);

        if ($allocated !== $total) {
            $over = $allocated > $total;
            throw ValidationException::withMessages([
                'questions' => sprintf(
                    'The marks must add up to %d. You have allocated %d, which is %d %s. %s',
                    $total,
                    $allocated,
                    abs($allocated - $total),
                    $over ? 'too many' : 'short',
                    $over ? 'Reduce some weights.' : 'Give the remaining marks to a question.',
                ),
            ]);
        }

        // An option may not be worth more than its own question.
        foreach ($data['questions'] as $i => $q) {
            foreach ($q['options'] ?? [] as $oi => $opt) {
                if (isset($opt['marks']) && (float) $opt['marks'] > (float) $q['weight']) {
                    throw ValidationException::withMessages([
                        "questions.{$i}.options.{$oi}.marks" => sprintf(
                            'That answer is worth %s, but the question is only worth %d.',
                            $opt['marks'], $q['weight']
                        ),
                    ]);
                }
            }
        }

        return $data;
    }
    // ----------------------------------------------------------------
    // GET /recruitment/jobs/{job}/shortlisting
    // Show existing criteria or the create form
    // ----------------------------------------------------------------
    public function manage(JobPosting $job)
    {
        $criteria = ShortlistingCriteria::with('questions')
            ->where('job_posting_id', $job->id)
            ->latest()
            ->first();

        // How many have applied, so "shortlist the top N" can be set against
        // a real number rather than guessed at.
        $job->loadCount('candidates');

        return view('recruitment.shortlisting.manage', compact('job', 'criteria'));
    }

    // ----------------------------------------------------------------
    // POST /recruitment/jobs/{job}/shortlisting
    // Create or replace criteria + questions for a job
    // ----------------------------------------------------------------
    public function store(Request $request, JobPosting $job)
    {
        $data = $this->validateCriteria($request);

        DB::transaction(function () use ($data, $job, $request) {
            // Deactivate any existing criteria
            ShortlistingCriteria::where('job_posting_id', $job->id)->update(['is_active' => false]);

            $criteria = ShortlistingCriteria::create([
                'job_posting_id' => $job->id,
                'title'          => $data['title'],
                'description'    => $data['description'] ?? null,
                'top_n'          => $data['top_n'],
                'total_marks'    => $data['total_marks'],
                'is_active'      => true,
                'created_by'     => auth()->id(),
            ]);

            foreach ($data['questions'] as $i => $q) {
                $this->writeQuestion($criteria, $q, $i);
            }
        });

        return redirect()->route('recruitment.shortlisting.manage', $job)
            ->with('success', 'Shortlisting criteria saved successfully.');
    }

    // ----------------------------------------------------------------
    // POST /recruitment/jobs/{job}/shortlisting/{criteria}
    // Update existing criteria
    // ----------------------------------------------------------------
    public function update(Request $request, JobPosting $job, ShortlistingCriteria $criteria)
    {
        $data = $this->validateCriteria($request);

        DB::transaction(function () use ($data, $criteria) {
            $criteria->update([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'top_n'       => $data['top_n'],
                'total_marks' => $data['total_marks'],
                'is_active'   => $request->boolean('is_active', true),
            ]);

            // Replace all questions
            $criteria->questions()->delete();

            foreach ($data['questions'] as $i => $q) {
                $this->writeQuestion($criteria, $q, $i);
            }

            $criteria->load('questions');

            // Recalculate all existing responses against new questions
            foreach ($criteria->responses as $response) {
                $response->recalculate();
            }
        });

        return redirect()->route('recruitment.shortlisting.manage', $job)
            ->with('success', 'Shortlisting criteria updated and scores recalculated.');
    }

    // ----------------------------------------------------------------
    // DELETE /recruitment/jobs/{job}/shortlisting/{criteria}
    // ----------------------------------------------------------------
    public function destroy(JobPosting $job, ShortlistingCriteria $criteria)
    {
        $criteria->delete();
        return redirect()->route('recruitment.shortlisting.manage', $job)
            ->with('success', 'Shortlisting criteria deleted.');
    }

    // ----------------------------------------------------------------
    // GET /recruitment/jobs/{job}/shortlisting/results
    // Ranked leaderboard of all candidates
    // ----------------------------------------------------------------
    public function results(Request $request, JobPosting $job)
    {
        $criteria = ShortlistingCriteria::with('questions')
            ->where('job_posting_id', $job->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$criteria) {
            return redirect()->route('recruitment.shortlisting.manage', $job)
                ->with('error', 'No active shortlisting criteria found for this job.');
        }

        // Candidates with responses — ranked by percentage
        $withResponses = ShortlistingResponse::with('candidate')
            ->where('criteria_id', $criteria->id)
            ->orderByDesc('percentage')
            ->get()
            ->map(function ($response, $index) {
                $response->rank = $index + 1;
                return $response;
            });

        // Candidates who applied but didn't fill out the screening
        $respondedIds = $withResponses->pluck('candidate_id');
        $withoutResponses = Candidate::where('job_posting_id', $job->id)
            ->whereNotIn('id', $respondedIds)
            ->get();

        $topN = $request->integer('top', $criteria->top_n);

        return view('recruitment.shortlisting.results', compact(
            'job', 'criteria', 'withResponses', 'withoutResponses', 'topN'
        ));
    }

    // ----------------------------------------------------------------
    // POST /recruitment/jobs/{job}/shortlisting/auto-shortlist
    // Mark top N candidates as "shortlisted"
    // ----------------------------------------------------------------
    public function autoShortlist(Request $request, JobPosting $job)
    {
        $criteria = ShortlistingCriteria::where('job_posting_id', $job->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$criteria) {
            return back()->with('error', 'No active criteria found.');
        }

        $request->validate(['top_n' => 'nullable|integer|min:1|max:1000']);

        $topN = $request->integer('top_n') ?: $criteria->top_n;

        $topResponses = ShortlistingResponse::where('criteria_id', $criteria->id)
            ->orderByDesc('percentage')
            ->take($topN)
            ->pluck('candidate_id');

        // One at a time through the pipeline rather than a mass update. A
        // mass update changed the column and told nobody: the whole point of
        // shortlisting is that the people on the list, and the people not on
        // it, find out.
        $pipeline = app(RecruitmentPipeline::class);

        $shortlisted = 0;
        foreach (Candidate::whereIn('id', $topResponses)
                     ->whereIn('status', ['new', 'screening'])->get() as $candidate) {
            if ($pipeline->moveTo($candidate, 'shortlisted')) $shortlisted++;
        }

        // The rest, but only those who actually completed the screening and
        // have not already moved into a later stage.
        $declined   = 0;
        $restIds    = ShortlistingResponse::where('criteria_id', $criteria->id)
            ->whereNotIn('candidate_id', $topResponses)
            ->pluck('candidate_id');

        foreach (Candidate::whereIn('id', $restIds)
                     ->whereIn('status', ['new', 'screening'])->get() as $candidate) {
            if ($pipeline->moveTo($candidate, 'rejected')) $declined++;
        }

        return redirect()->route('recruitment.shortlisting.results', $job)
            ->with('success', "{$shortlisted} shortlisted, {$declined} not taken forward. Everybody affected has been told.");
    }

    // ----------------------------------------------------------------
    // Called from CareersController after creating a candidate
    // Saves screening answers and calculates score
    // ----------------------------------------------------------------
    public static function saveResponses(Candidate $candidate, ShortlistingCriteria $criteria, array $answers): ShortlistingResponse
    {
        $criteria->load('questions');
        $totalScore = 0;
        $maxScore   = 0;

        $scoredAnswers = [];
        foreach ($criteria->questions as $question) {
            $answer = $answers[$question->id] ?? null;
            $scoredAnswers[$question->id] = $answer;
            $result = $question->scoreAnswer($answer);
            $totalScore += $result['earned'];
            $maxScore   += $result['max'];
        }

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        return ShortlistingResponse::create([
            'candidate_id' => $candidate->id,
            'criteria_id'  => $criteria->id,
            'answers'      => $scoredAnswers,
            'total_score'  => $totalScore,
            'max_score'    => $maxScore,
            'percentage'   => $percentage,
        ]);
    }
}
