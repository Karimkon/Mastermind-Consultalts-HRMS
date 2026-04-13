<?php
namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\Request;

class JobPostingController extends Controller
{
    public function index(Request $request)
    {
        $query = JobPosting::with(["department"])->withCount("candidates");
        if ($request->filled("status")) $query->where("status", $request->status);
        if ($request->filled("department_id")) $query->where("department_id", $request->department_id);
        $jobs = $query->orderByDesc("created_at")->paginate(20);
        $departments = Department::where("is_active",true)->get();
        return view("recruitment.jobs.index", compact("jobs","departments"));
    }
    public function create() {
        $departments  = Department::where("is_active",true)->get();
        $designations = Designation::where("is_active",true)->get();
        return view("recruitment.jobs.create", compact("departments","designations"));
    }
    public function store(Request $request) {
        $data = $request->validate(["title" => "required|string|max:200", "department_id" => "required|exists:departments,id", "employment_type" => "required", "description" => "required|string", "requirements" => "nullable|string", "vacancies" => "required|integer|min:1", "deadline" => "nullable|date"]);
        JobPosting::create(array_merge($data, ["status" => "draft", "created_by" => auth()->id()]));
        return redirect()->route("jobs.index")->with("success","Job posting created.");
    }
    public function show(JobPosting $job) {
        $job->load(["department","candidates"]);
        return view("recruitment.jobs.show", compact("job"));
    }
    public function edit(JobPosting $job) {
        $departments = Department::where("is_active",true)->get();
        $designations = Designation::where("is_active",true)->get();
        return view("recruitment.jobs.edit", compact("job","departments","designations"));
    }
    public function update(Request $request, JobPosting $job) {
        $data = $request->validate(["title" => "required|string|max:200", "department_id" => "required|exists:departments,id", "description" => "required|string", "status" => "required|in:draft,open,closed,filled", "vacancies" => "required|integer|min:1", "deadline" => "nullable|date"]);
        $job->update($data);
        return redirect()->route("jobs.show", $job)->with("success","Job updated.");
    }
    public function destroy(JobPosting $job) { $job->delete(); return redirect()->route("jobs.index")->with("success","Deleted."); }
    public function toggleStatus(JobPosting $job) {
        $job->update(["status" => $job->status === "open" ? "closed" : "open"]);
        return back()->with("success","Status toggled.");
    }
}
