<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Candidate, JobPosting};
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AiApiController extends Controller
{
    public function __construct(private AiService $ai) {}

    public function scoreCandidate(Candidate $candidate)
    {
        if (!$candidate->resume_path) {
            return response()->json(['message' => 'No resume uploaded.'], 422);
        }

        $job = $candidate->jobPosting;
        if (!$job) {
            return response()->json(['message' => 'No job posting linked.'], 422);
        }

        $resumePath = storage_path('app/public/' . $candidate->resume_path);
        if (!file_exists($resumePath)) {
            return response()->json(['message' => 'Resume file not found.'], 422);
        }

        $resumeText = file_get_contents($resumePath);
        $result     = $this->ai->scoreResume($resumeText, $job->requirements ?? '', $job->title);

        $candidate->update([
            'score'           => $result['score'] ?? $candidate->score,
            'score_breakdown' => json_encode($result['breakdown'] ?? []),
        ]);

        return response()->json(['data' => $result]);
    }

    public function shortlistCandidates(JobPosting $job)
    {
        $candidates = $job->candidates()->where('status', 'new')->get();
        if ($candidates->isEmpty()) {
            return response()->json(['message' => 'No unscored candidates found.'], 422);
        }

        $result = $this->ai->shortlistCandidates($job, $candidates);

        // Update scores from AI ranking
        if (!empty($result['ranked'])) {
            foreach ($result['ranked'] as $ranked) {
                if (!empty($ranked['candidate_id']) && isset($ranked['score'])) {
                    Candidate::where('id', $ranked['candidate_id'])
                        ->update(['score' => $ranked['score']]);
                }
            }
        }

        return response()->json(['data' => $result]);
    }

    public function interviewQuestions(Candidate $candidate)
    {
        $job = $candidate->jobPosting;
        if (!$job) {
            return response()->json(['message' => 'No job posting linked.'], 422);
        }

        $result = $this->ai->generateInterviewQuestions($candidate, $job);
        return response()->json(['data' => $result]);
    }
}
