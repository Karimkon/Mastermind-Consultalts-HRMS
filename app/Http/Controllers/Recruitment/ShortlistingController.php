<?php
namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\{JobPosting, ShortlistingCriteria, ShortlistingQuestion, ShortlistingResponse, Candidate};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShortlistingController extends Controller
{
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

        return view('recruitment.shortlisting.manage', compact('job', 'criteria'));
    }

    // ----------------------------------------------------------------
    // POST /recruitment/jobs/{job}/shortlisting
    // Create or replace criteria + questions for a job
    // ----------------------------------------------------------------
    public function store(Request $request, JobPosting $job)
    {
        $data = $request->validate([
            'title'                       => 'required|string|max:255',
            'description'                 => 'nullable|string|max:1000',
            'top_n'                       => 'required|integer|min:1|max:100',
            'is_active'                   => 'nullable|boolean',
            'questions'                   => 'required|array|min:1|max:15',
            'questions.*.question'        => 'required|string|max:500',
            'questions.*.question_type'   => 'required|in:multiple_choice,yes_no,scale,text',
            'questions.*.weight'          => 'required|integer|min:1|max:10',
            'questions.*.options'         => 'nullable|array',
            'questions.*.options.*.text'  => 'required_with:questions.*.options|string|max:255',
            'questions.*.correct_answer'  => 'nullable|string|max:50',
        ]);

        DB::transaction(function () use ($data, $job, $request) {
            // Deactivate any existing criteria
            ShortlistingCriteria::where('job_posting_id', $job->id)->update(['is_active' => false]);

            $criteria = ShortlistingCriteria::create([
                'job_posting_id' => $job->id,
                'title'          => $data['title'],
                'description'    => $data['description'] ?? null,
                'top_n'          => $data['top_n'],
                'is_active'      => true,
                'created_by'     => auth()->id(),
            ]);

            foreach ($data['questions'] as $i => $q) {
                $options = null;
                if ($q['question_type'] === 'multiple_choice' && !empty($q['options'])) {
                    // Mark correct option based on correct_answer index
                    $correctIdx = (int) ($q['correct_answer'] ?? 0);
                    $options    = collect($q['options'])->map(function ($opt, $idx) use ($correctIdx) {
                        return ['text' => $opt['text'], 'is_correct' => $idx === $correctIdx];
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
                    'weight'         => $q['weight'],
                    'sort_order'     => $i,
                ]);
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
        $data = $request->validate([
            'title'                       => 'required|string|max:255',
            'description'                 => 'nullable|string|max:1000',
            'top_n'                       => 'required|integer|min:1|max:100',
            'is_active'                   => 'nullable|boolean',
            'questions'                   => 'required|array|min:1|max:15',
            'questions.*.question'        => 'required|string|max:500',
            'questions.*.question_type'   => 'required|in:multiple_choice,yes_no,scale,text',
            'questions.*.weight'          => 'required|integer|min:1|max:10',
            'questions.*.options'         => 'nullable|array',
            'questions.*.options.*.text'  => 'required_with:questions.*.options|string|max:255',
            'questions.*.correct_answer'  => 'nullable|string|max:50',
        ]);

        DB::transaction(function () use ($data, $criteria) {
            $criteria->update([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'top_n'       => $data['top_n'],
                'is_active'   => $request->boolean('is_active', true),
            ]);

            // Replace all questions
            $criteria->questions()->delete();

            foreach ($data['questions'] as $i => $q) {
                $options = null;
                if ($q['question_type'] === 'multiple_choice' && !empty($q['options'])) {
                    $correctIdx = (int) ($q['correct_answer'] ?? 0);
                    $options    = collect($q['options'])->map(function ($opt, $idx) use ($correctIdx) {
                        return ['text' => $opt['text'], 'is_correct' => $idx === $correctIdx];
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
                    'weight'         => $q['weight'],
                    'sort_order'     => $i,
                ]);
            }

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

        $topN = $request->integer('top_n', $criteria->top_n);

        $topResponses = ShortlistingResponse::where('criteria_id', $criteria->id)
            ->orderByDesc('percentage')
            ->take($topN)
            ->pluck('candidate_id');

        // Mark top N as shortlisted
        Candidate::whereIn('id', $topResponses)
            ->whereIn('status', ['new', 'screening'])
            ->update(['status' => 'shortlisted']);

        // Mark the rest as rejected (only those who completed screening and aren't already in a later stage)
        ShortlistingResponse::where('criteria_id', $criteria->id)
            ->whereNotIn('candidate_id', $topResponses)
            ->pluck('candidate_id')
            ->each(function ($candidateId) {
                Candidate::where('id', $candidateId)
                    ->whereIn('status', ['new', 'screening'])
                    ->update(['status' => 'rejected']);
            });

        return redirect()->route('recruitment.shortlisting.results', $job)
            ->with('success', "Top {$topN} candidates have been shortlisted automatically.");
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
