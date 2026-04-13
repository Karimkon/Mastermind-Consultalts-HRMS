<?php
namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\Certification;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $query = TrainingCourse::withCount("enrollments");
        if ($request->filled("category")) $query->where("category",$request->category);
        $courses = $query->where("is_active",true)->orderBy("title")->paginate(20);
        return view("training.index", compact("courses"));
    }
    public function create()  { return view("training.create"); }
    public function store(Request $request) {
        $data = $request->validate(["title" => "required|string|max:200", "description" => "nullable|string", "category" => "nullable|string|max:100", "provider" => "nullable|string|max:200", "duration_hours" => "numeric|min:0", "cost" => "numeric|min:0", "is_mandatory" => "boolean"]);
        TrainingCourse::create(array_merge($data, ["is_active" => true]));
        return redirect()->route("training.index")->with("success","Course created.");
    }
    public function show(TrainingCourse $training) {
        $training->load(["enrollments.employee"]);
        $employees = Employee::where("status","active")->orderBy("first_name")->get();
        return view("training.show", compact("training","employees"));
    }
    public function edit(TrainingCourse $training) { return view("training.edit", compact("training")); }
    public function update(Request $request, TrainingCourse $training) {
        $training->update($request->validate(["title" => "required|string|max:200", "description" => "nullable|string", "is_active" => "boolean"]));
        return back()->with("success","Updated.");
    }
    public function destroy(TrainingCourse $training) { $training->delete(); return redirect()->route("training.index")->with("success","Deleted."); }

    public function enroll(Request $request, TrainingCourse $course) {
        $request->validate(["employee_id" => "required|exists:employees,id"]);
        TrainingEnrollment::firstOrCreate(["employee_id" => $request->employee_id, "course_id" => $course->id], ["status" => "enrolled", "enrolled_at" => now(), "assigned_by" => auth()->id()]);
        return back()->with("success","Employee enrolled.");
    }
    public function enrollBulk(Request $request, TrainingCourse $course) {
        $request->validate(["employee_ids" => "required|array"]);
        foreach ($request->employee_ids as $eid) {
            TrainingEnrollment::firstOrCreate(["employee_id" => $eid, "course_id" => $course->id], ["status" => "enrolled", "enrolled_at" => now(), "assigned_by" => auth()->id()]);
        }
        return back()->with("success","Employees enrolled.");
    }
    public function updateProgress(Request $request, TrainingEnrollment $enrollment) {
        $request->validate(["progress_pct" => "required|integer|between:0,100"]);
        $enrollment->update(["progress_pct" => $request->progress_pct, "status" => $request->progress_pct > 0 ? "in_progress" : "enrolled"]);
        return back()->with("success","Progress updated.");
    }
    public function markComplete(Request $request, TrainingEnrollment $enrollment) {
        $enrollment->update(["status" => "completed", "progress_pct" => 100, "completed_at" => now(), "score" => $request->score]);
        return back()->with("success","Marked complete.");
    }

    public function certifications() {
        $certifications = Certification::with("employee")->orderByDesc("issue_date")->paginate(25);
        return view("training.certifications", compact("certifications"));
    }
    public function storeCertification(Request $request) {
        $data = $request->validate(["employee_id" => "required|exists:employees,id", "name" => "required|string|max:200", "issued_by" => "required|string|max:200", "issue_date" => "required|date", "expiry_date" => "nullable|date", "certificate_number" => "nullable|string|max:100"]);
        if ($request->hasFile("document")) {
            $data["document_path"] = $request->file("document")->store("certifications","public");
        }
        Certification::create($data);
        return back()->with("success","Certification added.");
    }
    public function destroyCertification(Certification $certification) { $certification->delete(); return back()->with("success","Deleted."); }
}
