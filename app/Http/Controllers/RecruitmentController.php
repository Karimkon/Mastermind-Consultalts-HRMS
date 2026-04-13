<?php
namespace App\Http\Controllers;

use App\Models\{JobPosting, Candidate, Interview, Department, Employee};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecruitmentController extends Controller
{
    // Jobs
    public function jobsIndex(Request $request)
    {
        $jobs = JobPosting::with(['department'])->withCount('candidates')
            ->when($request->search, fn($q) => $q->where('title','like',"%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->paginate(15);
        $departments = Department::all();
        return view('recruitment.jobs.index', compact('jobs', 'departments'));
    }

    public function jobsCreate() { return view('recruitment.jobs.create', ['departments' => Department::all()]); }

    public function jobsStore(Request $request)
    {
        $request->validate(['title'=>'required','type'=>'required']);
        JobPosting::create($request->all());
        return redirect()->route('recruitment.jobs.index')->with('success', 'Job posted.');
    }

    public function jobsShow(JobPosting $job)
    {
        $candidates = $job->candidates()->orderByDesc('score')->paginate(20);
        return view('recruitment.jobs.show', compact('job', 'candidates'));
    }

    public function jobsEdit(JobPosting $job) { return view('recruitment.jobs.edit', ['job' => $job, 'departments' => Department::all()]); }

    public function jobsUpdate(Request $request, JobPosting $job)
    {
        $job->update($request->all());
        return redirect()->route('recruitment.jobs.show', $job)->with('success', 'Job updated.');
    }

    public function jobsDestroy(JobPosting $job) { $job->delete(); return redirect()->route('recruitment.jobs.index')->with('success', 'Deleted.'); }

    // Candidates
    public function candidatesIndex(Request $request)
    {
        $candidates = Candidate::with('jobPosting')
            ->when($request->search, fn($q) => $q->where('name','like',"%{$request->search}%")->orWhere('email','like',"%{$request->search}%"))
            ->when($request->job_posting_id, fn($q) => $q->where('job_posting_id', $request->job_posting_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('score')->paginate(25);
        $jobs = JobPosting::where('status','open')->get();
        return view('recruitment.candidates.index', compact('candidates', 'jobs'));
    }

    public function candidatesCreate() { return view('recruitment.candidates.create', ['jobs' => JobPosting::where('status','open')->get()]); }

    public function candidatesStore(Request $request)
    {
        $request->validate(['name'=>'required','email'=>'required|email','job_posting_id'=>'required']);
        $data = $request->only('name','email','phone','job_posting_id','notes');
        if ($request->hasFile('resume')) {
            $data['resume_path'] = $request->file('resume')->store('candidates/resumes', 'public');
        }
        // AI scoring
        $job = JobPosting::find($request->job_posting_id);
        $data['score'] = $this->scoreResume($request->notes ?? '', $job?->requirements ?? '');
        Candidate::create($data);
        return redirect()->route('recruitment.candidates.index')->with('success', 'Candidate added.');
    }

    public function candidatesShow(Candidate $candidate)
    {
        $candidate->load('jobPosting', 'interviews.interviewer');
        return view('recruitment.candidates.show', compact('candidate'));
    }

    public function candidatesUpdate(Request $request, Candidate $candidate)
    {
        $candidate->update($request->only('status', 'notes'));
        return back()->with('success', 'Status updated.');
    }

    // Interviews
    public function interviewsIndex(Request $request)
    {
        $interviews = Interview::with(['candidate.jobPosting','interviewer'])->orderByDesc('scheduled_at')->paginate(25);
        return view('recruitment.interviews.index', compact('interviews'));
    }

    public function interviewsCreate()
    {
        $candidates = Candidate::whereIn('status',['applied','screening','interview'])->get();
        $employees  = Employee::with('user')->where('status','active')->get();
        return view('recruitment.interviews.create', compact('candidates', 'employees'));
    }

    public function interviewsStore(Request $request)
    {
        $request->validate(['candidate_id'=>'required','interviewer_id'=>'required','scheduled_at'=>'required|date','type'=>'required']);
        Interview::create($request->only('candidate_id','interviewer_id','scheduled_at','type','notes') + ['status'=>'scheduled']);
        Candidate::find($request->candidate_id)->update(['status'=>'interview']);
        return redirect()->route('recruitment.interviews.index')->with('success', 'Interview scheduled.');
    }

    public function interviewsShow(Interview $interview)
    {
        $interview->load('candidate.jobPosting','interviewer');
        return view('recruitment.interviews.show', compact('interview'));
    }

    public function interviewsUpdate(Request $request, Interview $interview)
    {
        $interview->update($request->only('status','rating','feedback'));
        return back()->with('success', 'Feedback saved.');
    }

    private function scoreResume(string $resumeText, string $requirements): int
    {
        if (!$requirements) return 0;
        $keywords = array_filter(array_map('trim', preg_split('/[\n,;]+/', strtolower($requirements))));
        $text     = strtolower($resumeText);
        $matched  = 0;
        foreach ($keywords as $kw) { if ($kw && str_contains($text, $kw)) $matched++; }
        $total = count($keywords);
        return $total > 0 ? min(100, intval(($matched / $total) * 100)) : 0;
    }
}