<?php
namespace App\Http\Controllers;

use App\Models\{Candidate, JobPosting};
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AiRecruitmentController extends Controller
{
    public function __construct(private AiService $ai) {}

    /**
     * Score a single candidate's resume with AI.
     * POST /recruitment/ai/score/{candidate}
     */
    public function score(Candidate $candidate)
    {
        $job = $candidate->jobPosting;

        if (!$candidate->resume_path) {
            return response()->json(['error' => 'No resume uploaded for this candidate.'], 422);
        }

        // Read resume text from storage
        $resumeText = $this->extractResumeText($candidate->resume_path);

        if (!$resumeText) {
            return response()->json(['error' => 'Could not read resume file. Please ensure it is a text-based PDF or document.'], 422);
        }

        $result = $this->ai->scoreResume(
            $resumeText,
            $job->requirements ?? '',
            $job->title
        );

        if (isset($result['error'])) {
            return response()->json($result, 500);
        }

        // Update candidate score and breakdown
        $candidate->update([
            'score'          => $result['score'],
            'score_breakdown'=> json_encode($result),
        ]);

        return response()->json($result);
    }

    /**
     * AI-rank all shortlisted candidates for a job.
     * POST /recruitment/ai/shortlist/{job}
     */
    public function shortlist(JobPosting $job)
    {
        $candidates = Candidate::where('job_posting_id', $job->id)
            ->whereIn('status', ['screening', 'shortlisted', 'new'])
            ->get();

        if ($candidates->isEmpty()) {
            return response()->json(['error' => 'No candidates found for this job.'], 422);
        }

        $result = $this->ai->shortlistCandidates($job, $candidates);

        if (isset($result['error'])) {
            return response()->json($result, 500);
        }

        return response()->json(['rankings' => $result]);
    }

    /**
     * Generate interview questions for a candidate.
     * POST /recruitment/ai/questions/{candidate}
     */
    public function questions(Candidate $candidate)
    {
        $job = $candidate->jobPosting;
        $result = $this->ai->generateInterviewQuestions($candidate, $job);

        if (isset($result['error'])) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    private function extractResumeText(string $path): ?string
    {
        $fullPath = storage_path('app/public/' . $path);

        if (!file_exists($fullPath)) {
            $fullPath = public_path('storage/' . $path);
        }

        if (!file_exists($fullPath)) {
            return null;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // For plain text extraction — works for .txt files
        if ($ext === 'txt') {
            return file_get_contents($fullPath);
        }

        // For PDF: try pdftotext if available, else read raw bytes (Claude handles it)
        if ($ext === 'pdf') {
            $text = shell_exec("pdftotext " . escapeshellarg($fullPath) . " - 2>/dev/null");
            if ($text) return $text;
            // Fallback: read first 50KB of raw PDF (Claude can often parse text from PDFs)
            return substr(file_get_contents($fullPath), 0, 50000);
        }

        // For .doc/.docx: read as binary (limited but better than nothing)
        return substr(file_get_contents($fullPath), 0, 50000);
    }
}
