<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{JobPosting, Candidate, Interview, Employee};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecruitmentApiController extends Controller
{
    // ===================== JOBS =====================
    public function jobsIndex(Request $request)
    {
        $query = JobPosting::with('department');
        if ($request->search) $query->where('title', 'like', "%{$request->search}%");
        if ($request->status) $query->where('status', $request->status);

        return response()->json([
            'data' => $query->latest()->paginate(15)->through(fn($j) => $this->formatJob($j)),
        ]);
    }

    public function jobsStore(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'status'      => 'in:draft,open,closed,filled',
        ]);
        $job = JobPosting::create($request->only([
            'title','department_id','designation_id','employment_type','location',
            'description','requirements','benefits','salary_min','salary_max',
            'vacancies','deadline','status',
        ]) + ['status' => $request->status ?? 'open', 'created_by' => auth()->id()]);

        return response()->json(['data' => $this->formatJob($job->load('department'))], 201);
    }

    public function jobsShow(JobPosting $job)
    {
        $job->load(['department', 'candidates' => fn($q) => $q->orderBy('score', 'desc')]);
        $data = $this->formatJob($job);
        $data['candidates'] = $job->candidates->map(fn($c) => $this->formatCandidate($c));
        return response()->json(['data' => $data]);
    }

    public function jobsUpdate(Request $request, JobPosting $job)
    {
        $job->update($request->only([
            'title','department_id','employment_type','location','description',
            'requirements','benefits','salary_min','salary_max','vacancies','deadline','status',
        ]));
        return response()->json(['data' => $this->formatJob($job->fresh()->load('department'))]);
    }

    public function jobsDestroy(JobPosting $job)
    {
        $job->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ===================== CANDIDATES =====================
    public function candidatesIndex(Request $request)
    {
        $query = Candidate::with('jobPosting');
        if ($request->search) $query->where(fn($q) => $q
            ->where('first_name','like',"%{$request->search}%")
            ->orWhere('last_name','like',"%{$request->search}%")
            ->orWhere('email','like',"%{$request->search}%")
        );
        if ($request->job_id)  $query->where('job_posting_id', $request->job_id);
        if ($request->status)  $query->where('status', $request->status);

        return response()->json([
            'data' => $query->orderBy('score', 'desc')->paginate(20)->through(fn($c) => $this->formatCandidate($c)),
        ]);
    }

    public function candidatesStore(Request $request)
    {
        $request->validate([
            'name'           => 'required|string',
            'email'          => 'required|email',
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $resumePath = null;
        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store('candidates/resumes', 'public');
        }

        $nameParts = explode(' ', trim($request->name ?? ''), 2);
        $candidate = Candidate::create([
            'first_name'     => $nameParts[0],
            'last_name'      => $nameParts[1] ?? '',
            'email'          => $request->email,
            'phone'          => $request->phone,
            'job_posting_id' => $request->job_posting_id,
            'notes'          => $request->notes,
            'resume_path'    => $resumePath,
            'status'         => 'new',
            'score'          => 0,
        ]);

        // Basic keyword score
        if ($resumePath) {
            $job = JobPosting::find($request->job_posting_id);
            if ($job?->requirements) {
                $score = $this->keywordScore($candidate->resume_path, $job->requirements);
                $candidate->update(['score' => $score]);
            }
        }

        return response()->json(['data' => $this->formatCandidate($candidate->fresh()->load('jobPosting'))], 201);
    }

    public function candidatesShow(Candidate $candidate)
    {
        $candidate->load(['jobPosting', 'interviews.interviewer']);
        $data = $this->formatCandidate($candidate);
        $data['score_breakdown'] = $candidate->score_breakdown ? json_decode($candidate->score_breakdown, true) : null;
        $data['interviews'] = $candidate->interviews->map(fn($i) => [
            'id'           => $i->id,
            'type'         => $i->type,
            'scheduled_at' => $i->scheduled_at,
            'interviewer'  => $i->interviewer?->full_name,
            'status'       => $i->status,
            'rating'       => $i->rating,
            'feedback'     => $i->feedback,
        ]);
        return response()->json(['data' => $data]);
    }

    public function candidatesUpdate(Request $request, Candidate $candidate)
    {
        $candidate->update($request->only(['status', 'notes']));
        return response()->json(['data' => $this->formatCandidate($candidate->fresh()->load('jobPosting'))]);
    }

    // ===================== OFFERS =====================
    public function offerStore(Request $request, Candidate $candidate)
    {
        $request->validate([
            'offer_amount' => 'required|numeric|min:0',
            'offer_date'   => 'required|date',
            'offer_expiry' => 'required|date|after:offer_date',
        ]);
        $candidate->update($request->only(['offer_amount','offer_date','offer_expiry']) + ['status' => 'offer']);
        return response()->json(['data' => $this->formatCandidate($candidate->fresh())]);
    }

    public function offerAccept(Candidate $candidate)
    {
        $candidate->update(['status' => 'hired']);
        return response()->json(['data' => $this->formatCandidate($candidate->fresh())]);
    }

    public function offerReject(Candidate $candidate)
    {
        $candidate->update(['status' => 'rejected', 'offer_amount' => null]);
        return response()->json(['data' => $this->formatCandidate($candidate->fresh())]);
    }

    // ===================== INTERVIEWS =====================
    public function interviewsIndex(Request $request)
    {
        $query = Interview::with(['candidate.jobPosting', 'interviewer.user']);
        if ($request->candidate_id) $query->where('candidate_id', $request->candidate_id);

        return response()->json([
            'data' => $query->orderBy('scheduled_at', 'desc')->paginate(20)->through(fn($i) => [
                'id'           => $i->id,
                'candidate'    => $i->candidate?->name,
                'job'          => $i->candidate?->jobPosting?->title,
                'type'         => $i->type,
                'scheduled_at' => $i->scheduled_at,
                'interviewer'  => $i->interviewer?->full_name,
                'status'       => $i->status,
                'rating'       => $i->rating,
                'feedback'     => $i->feedback,
            ]),
        ]);
    }

    public function interviewsStore(Request $request)
    {
        $request->validate([
            'candidate_id'  => 'required|exists:candidates,id',
            'interviewer_id'=> 'required|exists:employees,id',
            'scheduled_at'  => 'required|date',
            'type'          => 'required|in:phone,video,in_person,technical,hr',
        ]);
        $interview = Interview::create($request->only(['candidate_id','interviewer_id','scheduled_at','type','notes']) + [
            'status' => 'scheduled',
        ]);
        Candidate::where('id', $request->candidate_id)->update(['status' => 'interview']);
        return response()->json(['data' => ['id' => $interview->id]], 201);
    }

    public function interviewsUpdate(Request $request, Interview $interview)
    {
        $interview->update($request->only(['status','rating','feedback','notes']));
        return response()->json(['data' => ['id' => $interview->id, 'status' => $interview->fresh()->status]]);
    }

    private function formatJob(JobPosting $j): array
    {
        return [
            'id'              => $j->id,
            'title'           => $j->title,
            'reference_number'=> $j->reference_number,
            'department'      => $j->department?->name,
            'department_id'   => $j->department_id,
            'employment_type' => $j->employment_type,
            'location'        => $j->location,
            'description'     => $j->description,
            'requirements'    => $j->requirements,
            'salary_min'      => $j->salary_min,
            'salary_max'      => $j->salary_max,
            'vacancies'       => $j->vacancies,
            'deadline'        => $j->deadline,
            'status'          => $j->status,
            'candidates_count'=> $j->candidates()->count(),
            'is_public'       => (bool)$j->is_public,
        ];
    }

    private function formatCandidate(Candidate $c): array
    {
        return [
            'id'                     => $c->id,
            'name'                   => $c->name,
            'email'                  => $c->email,
            'phone'                  => $c->phone,
            'job_posting_id'         => $c->job_posting_id,
            'job_title'              => $c->jobPosting?->title,
            'score'                  => $c->score,
            'status'                 => $c->status,
            'notes'                  => $c->notes,
            'resume_url'             => $c->resume_path ? Storage::url($c->resume_path) : null,
            'offer_amount'           => $c->offer_amount,
            'client_shortlist_status'=> $c->client_shortlist_status,
            'created_at'             => $c->created_at?->format('Y-m-d'),
        ];
    }

    private function keywordScore(string $resumePath, string $requirements): int
    {
        $fullPath = storage_path('app/public/' . $resumePath);
        if (!file_exists($fullPath)) return 0;
        $text = strtolower(file_get_contents($fullPath));
        $keywords = preg_split('/[\n,;]+/', strtolower($requirements));
        $keywords = array_filter(array_map('trim', $keywords));
        if (empty($keywords)) return 0;
        $matched = count(array_filter($keywords, fn($kw) => str_contains($text, $kw)));
        return min(100, (int) round(($matched / count($keywords)) * 100));
    }
}
