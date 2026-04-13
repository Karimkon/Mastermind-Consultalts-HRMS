<?php
namespace App\Http\Controllers;

use App\Models\{PerformanceReview, PerformanceCycle, Kpi, Employee, Department};
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        $reviews = PerformanceReview::with(['employee.department','reviewer','cycle'])
            ->when($request->cycle_id, fn($q) => $q->where('cycle_id', $request->cycle_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')->paginate(25);
        $cycles = PerformanceCycle::all();
        $stats = ['Total Reviews' => PerformanceReview::count(), 'Average Score' => round(PerformanceReview::avg('total_score') ?? 0, 1), 'Active Cycles' => PerformanceCycle::where('status','active')->count()];
        return view('performance.index', compact('reviews','cycles','stats'));
    }

    public function create()
    {
        $employees = Employee::with('user')->where('status','active')->get();
        $cycles    = PerformanceCycle::where('status','active')->get();
        $kpis      = Kpi::all();
        return view('performance.create', compact('employees','cycles','kpis'));
    }

    public function store(Request $request)
    {
        $request->validate(['employee_id'=>'required','cycle_id'=>'required']);
        $ratings = $request->ratings ?? [];
        $total   = count($ratings) > 0 ? round(array_sum($ratings) / (count($ratings) * 5) * 100, 1) : 0;
        PerformanceReview::create(['employee_id'=>$request->employee_id,'reviewer_id'=>auth()->user()->employee?->id,'cycle_id'=>$request->cycle_id,'type'=>$request->type??'manager','ratings'=>$ratings,'comments'=>$request->comments,'total_score'=>$total]);
        return redirect()->route('performance.index')->with('success', 'Review submitted.');
    }

    public function show(PerformanceReview $performance)
    {
        $performance->load(['employee.department','reviewer','cycle']);
        $kpis = Kpi::all();
        return view('performance.show', ['review' => $performance, 'kpis' => $kpis]);
    }

    public function destroy(PerformanceReview $performance) { $performance->delete(); return back()->with('success', 'Deleted.'); }

    // KPIs
    public function kpis() { return view('performance.kpis', ['kpis' => Kpi::paginate(30)]); }

    public function storeKpi(Request $request)
    {
        $request->validate(['name'=>'required','category'=>'required']);
        Kpi::create($request->only('name','category','weight','description'));
        return back()->with('success', 'KPI added.');
    }

    public function destroyKpi(Kpi $kpi) { $kpi->delete(); return back()->with('success', 'Deleted.'); }

    // Cycles
    public function cyclesIndex() { return view('performance.cycles', ['cycles' => PerformanceCycle::orderByDesc('year')->get()]); }

    public function cyclesCreate() { return view('performance.cycle-create'); }

    public function cyclesStore(Request $request)
    {
        $request->validate(['name'=>'required','year'=>'required']);
        PerformanceCycle::create($request->only('name','year','start_date','end_date','status'));
        return redirect()->route('performance.cycles.index')->with('success', 'Cycle created.');
    }

    public function cyclesEdit(PerformanceCycle $cycle) { return view('performance.cycle-create', compact('cycle')); }

    public function cyclesUpdate(Request $request, PerformanceCycle $cycle)
    {
        $cycle->update($request->only('name','year','start_date','end_date','status'));
        return redirect()->route('performance.cycles.index')->with('success', 'Cycle updated.');
    }
}