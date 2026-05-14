<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{PerformanceReview, Kpi, PerformanceCycle, EmployeeGoal};
use Illuminate\Http\Request;

class PerformanceApiController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = PerformanceReview::with(['employee.user', 'cycle', 'reviewer.user']);

        if ($user->hasRole('employee') && !$user->hasRole(['super-admin','hr-admin','manager'])) {
            $query->where('employee_id', $user->employee?->id);
        }
        if ($request->cycle_id) $query->where('cycle_id', $request->cycle_id);

        return response()->json([
            'data' => $query->latest()->paginate(15)->through(fn($r) => [
                'id'          => $r->id,
                'employee'    => $r->employee?->full_name,
                'avatar_url'  => $r->employee?->user?->avatar_url,
                'cycle'       => $r->cycle?->name,
                'reviewer'    => $r->reviewer?->full_name,
                'total_score' => $r->total_score,
                'status'      => $r->status ?? 'draft',
                'review_date' => $r->created_at?->format('Y-m-d'),
            ]),
        ]);
    }

    public function kpis()
    {
        return response()->json([
            'data' => Kpi::orderBy('name')->get()->map(fn($k) => [
                'id'       => $k->id,
                'name'     => $k->name,
                'category' => $k->category,
                'weight'   => $k->weight,
            ]),
        ]);
    }

    public function cycles()
    {
        return response()->json([
            'data' => PerformanceCycle::orderBy('year', 'desc')->get()->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'year'     => $c->year,
                'is_active'=> $c->is_active ?? false,
            ]),
        ]);
    }

    public function goals(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['data' => []]);

        return response()->json([
            'data' => EmployeeGoal::where('employee_id', $employee->id)->latest()->get()->map(fn($g) => [
                'id'          => $g->id,
                'title'       => $g->title,
                'description' => $g->description,
                'target_date' => $g->target_date?->format('Y-m-d'),
                'progress'    => $g->progress ?? 0,
                'status'      => $g->status ?? 'active',
            ]),
        ]);
    }

    public function storeGoal(Request $request)
    {
        $request->validate(['title' => 'required|string']);
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        $goal = EmployeeGoal::create($request->only(['title','description','target_date']) + [
            'employee_id' => $employee->id,
            'progress'    => 0,
            'status'      => 'active',
        ]);

        return response()->json(['data' => ['id' => $goal->id, 'title' => $goal->title]], 201);
    }

    public function updateGoal(Request $request, EmployeeGoal $goal)
    {
        $goal->update($request->only(['title','description','target_date','progress','status']));
        return response()->json(['data' => ['id' => $goal->id]]);
    }

    public function report(Request $request)
    {
        $cycleId = $request->input('cycle_id');

        $reviews = PerformanceReview::with('employee')
            ->when($cycleId, fn($q) => $q->where('performance_cycle_id', $cycleId))
            ->get();

        $avgScore = $reviews->avg('overall_score') ?? 0;
        $distribution = $reviews->groupBy(fn($r) => match(true) {
            $r->overall_score >= 4.5 => 'Outstanding',
            $r->overall_score >= 3.5 => 'Exceeds Expectations',
            $r->overall_score >= 2.5 => 'Meets Expectations',
            $r->overall_score >= 1.5 => 'Needs Improvement',
            default                  => 'Unsatisfactory',
        })->map->count();

        $goals = EmployeeGoal::selectRaw('status, count(*) as count')->groupBy('status')->get()
            ->pluck('count','status');

        return response()->json([
            'data' => [
                'reviews_count'  => $reviews->count(),
                'average_score'  => round($avgScore, 2),
                'distribution'   => $distribution,
                'goals_completed'=> $goals->get('completed', 0),
                'goals_pending'  => $goals->get('in_progress', 0) + $goals->get('not_started', 0),
            ],
        ]);
    }
}
