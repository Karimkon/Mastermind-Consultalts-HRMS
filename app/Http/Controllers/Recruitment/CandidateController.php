<?php
namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CandidateController extends Controller
{
    public function index(Request $request)
    {
        $query = Candidate::with("jobPosting");
        if ($request->filled("job_posting_id")) $query->where("job_posting_id", $request->job_posting_id);
        if ($request->filled("status")) $query->where("status", $request->status);
        $candidates = $query->orderByDesc("score")->paginate(25);
        $jobs = JobPosting::where("status","open")->get();
        return view("recruitment.candidates.index", compact("candidates","jobs"));
    }
    public function create() { $jobs = JobPosting::where("status","open")->get(); return view("recruitment.candidates.create", compact("jobs")); }
    public function store(Request $request) {
        $data = $request->validate(["job_posting_id" => "required|exists:job_postings,id", "first_name" => "required|string|max:100", "last_name" => "required|string|max:100", "email" => "required|email", "phone" => "nullable|string|max:20", "cover_letter" => "nullable|string", "source" => "nullable|string|max:100"]);
        if ($request->hasFile("resume")) {
            $data["resume_path"] = $request->file("resume")->store("resumes","public");
        }
        $candidate = Candidate::create(array_merge($data, ["status" => "new"]));
        $this->scoreCandidate($candidate);
        return redirect()->route("candidates.show", $candidate)->with("success","Candidate added.");
    }
    public function show(Candidate $candidate) {
        $candidate->load(["jobPosting","interviews.interviewer"]);
        return view("recruitment.candidates.show", compact("candidate"));
    }
    public function edit(Candidate $candidate) { $jobs = JobPosting::where("status","open")->get(); return view("recruitment.candidates.edit", compact("candidate","jobs")); }
    public function update(Request $request, Candidate $candidate) {
        $candidate->update($request->validate(["first_name" => "required|string|max:100", "last_name" => "required|string|max:100", "email" => "required|email", "phone" => "nullable|string|max:20", "notes" => "nullable|string"]));
        return back()->with("success","Updated.");
    }
    public function destroy(Candidate $candidate) { $candidate->delete(); return redirect()->route("candidates.index")->with("success","Deleted."); }

    public function updateStatus(Request $request, Candidate $candidate) {
        $request->validate(["status" => "required|in:new,screening,shortlisted,interview,offer,hired,rejected"]);
        $candidate->update(["status" => $request->status]);
        return back()->with("success","Status updated.");
    }

    public function scheduleInterview(Request $request, Candidate $candidate) {
        $data = $request->validate(["scheduled_at" => "required|date", "interviewer_id" => "nullable|exists:employees,id", "type" => "required|in:phone,video,in_person,technical,hr", "location" => "nullable|string"]);
        Interview::create(array_merge($data, ["candidate_id" => $candidate->id, "status" => "scheduled"]));
        $candidate->update(["status" => "interview"]);
        return back()->with("success","Interview scheduled.");
    }

    public function calculateScore(Candidate $candidate) {
        $this->scoreCandidate($candidate);
        return back()->with("success","Score recalculated: " . $candidate->fresh()->score);
    }

    private function scoreCandidate(Candidate $candidate): void
    {
        $job  = $candidate->jobPosting;
        if (!$job || !$candidate->resume_path) { $candidate->update(["score" => 0]); return; }

        $keywords     = array_filter(array_map("trim", explode("\n", strtolower($job->requirements ?? ""))));
        $resumeText   = strtolower($candidate->cover_letter ?? "");
        $matched      = [];
        $score        = 0;

        foreach ($keywords as $kw) {
            if ($kw && str_contains($resumeText, $kw)) { $matched[] = $kw; $score += 10; }
        }

        $candidate->update(["score" => min(100, $score), "skills_matched" => $matched]);
    }
}
