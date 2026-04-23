<?php
namespace App\Http\Controllers;

use App\Models\{JobPosting, Candidate, Department, ShortlistingCriteria};
use App\Http\Controllers\Recruitment\ShortlistingController;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CareersController extends Controller
{
    public function index(Request $request)
    {
        $jobs = JobPosting::with("department")
            ->where("status", "open")
            ->where("is_public", true)
            ->when($request->department, fn($q) => $q->where("department_id", $request->department))
            ->when($request->type, fn($q) => $q->where("employment_type", $request->type))
            ->when($request->search, fn($q) => $q->where("title","like","%{$request->search}%")->orWhere("location","like","%{$request->search}%"))
            ->orderByDesc("created_at")
            ->paginate(12);

        $departments = Department::whereHas("jobPostings", fn($q) => $q->where("status","open")->where("is_public",true))->get();
        $totalJobs   = JobPosting::where("status","open")->where("is_public",true)->count();

        return view("careers.index", compact("jobs", "departments", "totalJobs"));
    }

    public function show(JobPosting $job)
    {
        abort_unless($job->status === "open" && $job->is_public, 404);
        $job->load("department");
        $relatedJobs = JobPosting::with("department")
            ->where("status","open")->where("is_public",true)
            ->where("id","!=",$job->id)
            ->where("department_id", $job->department_id)
            ->limit(3)->get();

        $screeningCriteria = ShortlistingCriteria::with('questions')
            ->where('job_posting_id', $job->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        return view("careers.show", compact("job", "relatedJobs", "screeningCriteria"));
    }

    public function apply(Request $request, JobPosting $job)
    {
        abort_unless($job->status === "open" && $job->is_public, 404);

        $request->validate([
            "name"         => "required|string|max:255",
            "email"        => "required|email|max:255",
            "phone"        => "nullable|string|max:20",
            "cover_letter" => "nullable|string|max:2000",
            "cv"           => "required|file|max:5120|mimes:pdf,doc,docx",
        ]);

        $cvFile = $request->file("cv");
        $cvPath = $cvFile->store("applications/" . $job->id, "local");

        $nameParts = explode(" ", $request->name, 2);
        $candidate = Candidate::create([
            "job_posting_id" => $job->id,
            "first_name"     => $nameParts[0],
            "last_name"      => $nameParts[1] ?? "",
            "email"          => $request->email,
            "phone"          => $request->phone,
            "cover_letter"   => $request->cover_letter,
            "resume_path"    => $cvPath,
            "status"         => "new",
            "source"         => "careers_page",
        ]);

        // Save screening questionnaire responses if criteria exists
        $criteria = ShortlistingCriteria::with('questions')
            ->where('job_posting_id', $job->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if ($criteria && $criteria->questions->isNotEmpty()) {
            $answers = [];
            foreach ($criteria->questions as $question) {
                $answers[$question->id] = $request->input("screening.{$question->id}");
            }
            ShortlistingController::saveResponses($candidate, $criteria, $answers);
        }

        app(NotificationService::class)->newApplication($candidate);

        return redirect()->route("careers.show", $job)
            ->with("success", "Your application has been submitted! We will be in touch soon.");
    }
}
