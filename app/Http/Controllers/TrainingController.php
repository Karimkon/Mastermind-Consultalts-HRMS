<?php
namespace App\Http\Controllers;

use App\Models\{TrainingCourse, TrainingEnrollment, Certification, Employee};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $courses = TrainingCourse::withCount('enrollments')
            ->when($request->search, fn($q) => $q->where('title','like',"%{$request->search}%"))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->paginate(12);
        return view('training.index', compact('courses'));
    }

    public function create() { return view('training.create'); }

    public function store(Request $request)
    {
        $request->validate(['title'=>'required','duration_hours'=>'required|numeric']);
        $data = $request->only('title','category','description','duration_hours');
        if ($request->hasFile('material')) {
            $data['material_path'] = $request->file('material')->store('training/materials', 'public');
        }
        TrainingCourse::create($data);
        return redirect()->route('training.index')->with('success', 'Course created.');
    }

    public function show(TrainingCourse $training)
    {
        $enrollments = $training->enrollments()->with('employee')->get();
        return view('training.show', ['course' => $training, 'enrollments' => $enrollments]);
    }

    public function edit(TrainingCourse $training) { return view('training.edit', ['course' => $training]); }

    public function update(Request $request, TrainingCourse $training)
    {
        $data = $request->only('title','category','description','duration_hours');
        if ($request->hasFile('material')) {
            if ($training->material_path) Storage::disk('public')->delete($training->material_path);
            $data['material_path'] = $request->file('material')->store('training/materials', 'public');
        }
        $training->update($data);
        return redirect()->route('training.show', $training)->with('success', 'Course updated.');
    }

    public function destroy(TrainingCourse $training) { $training->delete(); return back()->with('success', 'Deleted.'); }

    public function enroll(Request $request, TrainingCourse $training)
    {
        $employee = auth()->user()->employee;
        if (!$employee) return back()->with('error', 'No employee profile.');
        TrainingEnrollment::firstOrCreate(['employee_id'=>$employee->id,'course_id'=>$training->id],['status'=>'enrolled','progress_pct'=>0]);
        return back()->with('success', 'Enrolled in course.');
    }

    public function certifications(Request $request)
    {
        $employees      = Employee::with('user')->where('status','active')->get();
        $certifications = Certification::with(['employee'])
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->orderByDesc('issue_date')->paginate(25);
        return view('training.certifications', compact('employees','certifications'));
    }

    public function storeCertification(Request $request)
    {
        $request->validate(['employee_id'=>'required','name'=>'required']);
        $data = $request->only('employee_id','name','issued_by','issue_date','expiry_date');
        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('certifications', 'public');
        }
        Certification::create($data);
        return back()->with('success', 'Certification added.');
    }
}