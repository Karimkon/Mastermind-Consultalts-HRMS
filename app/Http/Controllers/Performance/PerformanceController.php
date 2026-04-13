<?php
namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Kpi;
use App\Models\PerformanceCycle;
use App\Models\EmployeeKpi;
use App\Models\PerformanceReview;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index()
    {
        $cycles = PerformanceCycle::withCount("reviews")->orderByDesc("year")->get();
        $activeCycle = PerformanceCycle::where("status","active")->first();
        return view("performance.index", compact("cycles","activeCycle"));
    }
    public function show(PerformanceCycle $performance) {
        $performance->load(["reviews.employee","kpis.employee","kpis.kpi"]);
        return view("performance.show", compact("performance"));
    }
    public function create()  { return view("performance.create"); }
    public function store(Request $request) { $this->storeCycle($request); return redirect()->route("performance.index"); }
    public function edit(PerformanceCycle $performance)   { return view("performance.edit", compact("performance")); }
    public function update(Request $request, PerformanceCycle $performance) { $performance->update($request->validate(["name" => "required|string", "status" => "required"])); return back()->with("success","Updated."); }
    public function destroy(PerformanceCycle $performance) { $performance->delete(); return redirect()->route("performance.index")->with("success","Deleted."); }

    public function kpis()
    {
        $kpis = Kpi::with("department")->orderBy("category")->paginate(25);
        $departments = \App\Models\Department::where("is_active",true)->get();
        return view("performance.kpis", compact("kpis","departments"));
    }
    public function storeKpi(Request $request) {
        $data = $request->validate(["name" => "required|string|max:200", "description" => "nullable|string", "category" => "required|in:financial,customer,internal_process,learning_growth", "weight" => "required|numeric|min:0|max:100", "unit" => "nullable|string|max:50", "department_id" => "nullable|exists:departments,id"]);
        Kpi::create(array_merge($data, ["is_active" => true]));
        return back()->with("success","KPI created.");
    }
    public function updateKpi(Request $request, Kpi $kpi) {
        $kpi->update($request->validate(["name" => "required|string|max:200", "weight" => "required|numeric|min:0|max:100", "is_active" => "boolean"]));
        return back()->with("success","KPI updated.");
    }

    public function cycles()
    {
        $cycles = PerformanceCycle::withCount("reviews")->orderByDesc("year")->get();
        return view("performance.cycles", compact("cycles"));
    }
    public function storeCycle(Request $request) {
        $data = $request->validate(["name" => "required|string", "year" => "required|integer|min:2020", "start_date" => "required|date", "end_date" => "required|date|after:start_date"]);
        $cycle = PerformanceCycle::create(array_merge($data, ["status" => "draft"]));
        if ($request->expectsJson()) return response()->json($cycle);
        return redirect()->route("performance.cycles")->with("success","Cycle created.");
    }

    public function submitReview(Request $request, Employee $employee)
    {
        $data = $request->validate(["cycle_id" => "required|exists:performance_cycles,id", "review_type" => "required|in:self,manager,peer,360", "ratings" => "required|array", "strengths" => "nullable|string", "improvements" => "nullable|string", "comments" => "nullable|string"]);
        $total = array_sum($data["ratings"]) / max(1, count($data["ratings"]));
        PerformanceReview::create(array_merge($data, ["employee_id" => $employee->id, "reviewer_id" => auth()->user()->employee?->id, "total_score" => round($total, 2), "status" => "submitted"]));
        return back()->with("success","Review submitted.");
    }
}
