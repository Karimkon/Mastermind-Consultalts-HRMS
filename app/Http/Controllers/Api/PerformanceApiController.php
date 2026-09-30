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

    /** The only values employee_goals.status will accept. */
    private const GOAL_STATUSES = ['not_started', 'in_progress', 'achieved', 'missed'];

    private function goalJson(EmployeeGoal $g): array
    {
        return [
            'id'          => $g->id,
            'title'       => $g->title,
            'description' => $g->description,
            'target_date' => $g->target_date?->format('Y-m-d'),
            'weight'      => (float) ($g->weight ?? 0),
            'progress'    => (int) ($g->progress ?? 0),
            'status'      => $g->status,
            'cycle'       => $g->cycle?->name,
            'set_by_me'   => $g->created_by === auth()->id(),
            'attachments' => $g->attachments()->count(),
            'created_at'  => $g->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * Yours, one you set for somebody who reports to you, or you are HR.
     *
     * The web has always checked this on update. The API checked nothing at
     * all: both the id and the new values came straight off the request, so
     * any signed-in user could rewrite or delete anybody's goal by its number.
     */
    private function authoriseGoal(EmployeeGoal $goal): void
    {
        $user     = auth()->user();
        $isAdmin  = $user->hasAnyRole(['super-admin', 'hr-admin', 'manager', 'md']);
        $isOwner  = $user->employee && $user->employee->id === $goal->employee_id;

        abort_unless($isOwner || $isAdmin || $goal->created_by === $user->id, 403, 'This goal is not yours.');
    }

    public function goals(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['data' => []]);

        $goals = EmployeeGoal::with('cycle')
            ->where('employee_id', $employee->id)
            ->latest()->get();

        return response()->json([
            'data'    => $goals->map(fn($g) => $this->goalJson($g)),
            'summary' => [
                'total'       => $goals->count(),
                'in_progress' => $goals->where('status', 'in_progress')->count(),
                'achieved'    => $goals->where('status', 'achieved')->count(),
                // Weighted average, the same figure the scorecard works from.
                'progress'    => $goals->isEmpty() ? 0 : (int) round($goals->avg('progress')),
            ],
        ]);
    }

    public function storeGoal(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'weight'      => 'nullable|numeric|min:0|max:100',
        ]);

        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        // status was hardcoded to 'active'. The column is an enum of
        // not_started/in_progress/achieved/missed, so every goal created from
        // the phone was rejected by the database.
        $goal = EmployeeGoal::create($data + [
            'employee_id' => $employee->id,
            'weight'      => $data['weight'] ?? 0,
            'progress'    => 0,
            'status'      => 'not_started',
            'created_by'  => auth()->id(),
        ]);

        return response()->json(['data' => $this->goalJson($goal)], 201);
    }

    public function updateGoal(Request $request, EmployeeGoal $goal)
    {
        $this->authoriseGoal($goal);

        $data = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'weight'      => 'nullable|numeric|min:0|max:100',
            'progress'    => 'sometimes|integer|min:0|max:100',
            'status'      => 'sometimes|in:' . implode(',', self::GOAL_STATUSES),
        ]);

        // Moving the bar off zero means it has been started; filling it means
        // it is done. Otherwise the list stays on "not started" forever while
        // the percentage climbs, which reads as broken.
        if (isset($data['progress']) && !isset($data['status'])) {
            $data['status'] = $data['progress'] >= 100 ? 'achieved'
                : ($data['progress'] > 0 ? 'in_progress' : $goal->status);
        }

        $goal->update($data);

        return response()->json(['data' => $this->goalJson($goal->fresh('cycle'))]);
    }

    /**
     * The route has always pointed here; the method did not exist, so deleting
     * a goal from the phone was a 500.
     */
    public function destroyGoal(EmployeeGoal $goal)
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager', 'md']),
            403, 'Only HR or a manager can delete a goal.'
        );

        $goal->delete();

        return response()->json(['message' => 'Goal deleted.']);
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
