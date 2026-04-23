<?php
namespace App\Services;

use App\Models\Candidate;
use App\Models\JobPosting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class AiService
{
    private string $apiKey;
    private string $model;
    private string $endpoint = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', '');
        $this->model  = config('services.anthropic.model', 'claude-sonnet-4-6');
    }

    /**
     * Score a resume against job requirements using Claude AI.
     * Returns ['score', 'summary', 'strengths', 'gaps', 'recommendation', 'error']
     */
    public function scoreResume(string $resumeText, string $jobRequirements, string $jobTitle = ''): array
    {
        if (empty($this->apiKey)) {
            return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY in .env'];
        }

        $prompt = <<<PROMPT
You are an expert HR recruiter and talent assessor. Analyze this candidate's resume against the job requirements and provide a structured evaluation.

JOB TITLE: {$jobTitle}

JOB REQUIREMENTS:
{$jobRequirements}

CANDIDATE RESUME:
{$resumeText}

Respond in valid JSON with exactly this structure:
{
  "score": <integer 0-100>,
  "summary": "<2-3 sentence overall assessment>",
  "strengths": ["<strength 1>", "<strength 2>", "<strength 3>"],
  "gaps": ["<gap 1>", "<gap 2>"],
  "recommendation": "<one of: Strongly Recommend | Recommend | Consider | Do Not Recommend>",
  "reasoning": "<1 paragraph explaining the score>"
}
PROMPT;

        $response = $this->callClaude($prompt);

        if (isset($response['error'])) {
            return $response;
        }

        $data = json_decode($response, true);
        if (!$data) {
            return ['error' => 'AI returned an invalid response. Please try again.'];
        }

        return $data;
    }

    /**
     * Rank and recommend candidates for a job using Claude AI.
     */
    public function shortlistCandidates(JobPosting $job, Collection $candidates): array
    {
        if (empty($this->apiKey)) {
            return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY in .env'];
        }

        $candidateList = $candidates->map(fn($c) => [
            'id'    => $c->id,
            'name'  => $c->name,
            'score' => $c->score,
            'notes' => $c->notes,
        ])->toJson(JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
You are a senior recruitment consultant. Rank and recommend these candidates for the following job.

JOB TITLE: {$job->title}
REQUIREMENTS: {$job->requirements}
EMPLOYMENT TYPE: {$job->employment_type}
SALARY RANGE: {$job->salary_min} - {$job->salary_max}

CANDIDATES (JSON with their details and AI keyword scores):
{$candidateList}

Respond in valid JSON as an array:
[
  {
    "candidate_id": <id>,
    "rank": <1 = top>,
    "recommendation": "<Strongly Recommend | Recommend | Consider | Do Not Recommend>",
    "reasoning": "<1-2 sentences explaining this ranking>"
  }
]

Rank ALL candidates provided.
PROMPT;

        $response = $this->callClaude($prompt);

        if (isset($response['error'])) {
            return $response;
        }

        $data = json_decode($response, true);
        if (!$data) {
            return ['error' => 'AI returned an invalid response. Please try again.'];
        }

        return $data;
    }

    /**
     * Generate tailored interview questions for a candidate.
     */
    public function generateInterviewQuestions(Candidate $candidate, JobPosting $job): array
    {
        if (empty($this->apiKey)) {
            return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY in .env'];
        }

        $prompt = <<<PROMPT
You are an expert interviewer. Generate tailored interview questions for this candidate applying for this role.

JOB TITLE: {$job->title}
JOB REQUIREMENTS: {$job->requirements}
JOB DESCRIPTION: {$job->description}

CANDIDATE NAME: {$candidate->name}
CANDIDATE NOTES: {$candidate->notes}
AI MATCH SCORE: {$candidate->score}/100

Generate questions in valid JSON:
{
  "technical": ["<question 1>", "<question 2>", "<question 3>", "<question 4>"],
  "behavioral": ["<question 1>", "<question 2>", "<question 3>"],
  "situational": ["<question 1>", "<question 2>", "<question 3>"],
  "culture_fit": ["<question 1>", "<question 2>"]
}

Make questions specific to the job requirements and candidate profile.
PROMPT;

        $response = $this->callClaude($prompt);

        if (isset($response['error'])) {
            return $response;
        }

        $data = json_decode($response, true);
        if (!$data) {
            return ['error' => 'AI returned an invalid response. Please try again.'];
        }

        return $data;
    }

    private function callClaude(string $prompt): string|array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post($this->endpoint, [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                return ['error' => 'AI API request failed: ' . $response->status()];
            }

            $body = $response->json();
            return $body['content'][0]['text'] ?? ['error' => 'No response from AI'];

        } catch (\Exception $e) {
            return ['error' => 'AI service error: ' . $e->getMessage()];
        }
    }
}
