<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{BscCycle, BscEntry, BscKra, Employee};
use Illuminate\Http\Request;

class BscApiController extends Controller
{
    // ─── Cycles ───────────────────────────────────────────────────────────────

    public function cycles()
    {
        $cycles = BscCycle::withCount('kras')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get()
            ->map(fn($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'year'       => $c->year,
                'period'     => $c->period,
                'status'     => $c->status,
                'start_date' => $c->start_date?->toDateString(),
                'end_date'   => $c->end_date?->toDateString(),
                'kras_count' => $c->kras_count,
            ]);

        return response()->json($cycles);
    }

    public function showCycle(BscCycle $cycle)
    {
        $cycle->load(['kras' => fn($q) => $q->orderBy('perspective')->orderBy('sort_order')]);

        $weights = $cycle->perspectiveWeights;
        $grouped = [];
        foreach ($cycle->kras as $kra) {
            $grouped[$kra->perspective][] = $this->formatKra($kra);
        }

        return response()->json([
            'id'           => $cycle->id,
            'name'         => $cycle->name,
            'year'         => $cycle->year,
            'period'       => $cycle->period,
            'status'       => $cycle->status,
            'start_date'   => $cycle->start_date?->toDateString(),
            'end_date'     => $cycle->end_date?->toDateString(),
            'weights'      => $weights,
            'perspectives' => $grouped,
        ]);
    }

    // ─── My Appraisal ─────────────────────────────────────────────────────────

    public function myAppraisal()
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'No employee record found.'], 404);
        }

        $cycle = BscCycle::where('status', 'active')->first();
        if (!$cycle) {
            return response()->json(['cycle' => null, 'entries' => [], 'overall_score' => 0]);
        }

        $cycle->load(['kras' => fn($q) => $q->orderBy('perspective')->orderBy('sort_order')]);

        $entries = BscEntry::with('kra')
            ->where('employee_id', $employee->id)
            ->whereHas('kra', fn($q) => $q->where('cycle_id', $cycle->id))
            ->get()
            ->keyBy('kra_id');

        $kraData = $cycle->kras->map(fn($kra) => [
            'kra'   => $this->formatKra($kra),
            'entry' => isset($entries[$kra->id]) ? $this->formatEntry($entries[$kra->id]) : null,
        ]);

        $overallScore = $entries->sum('weighted_index');

        return response()->json([
            'cycle'         => [
                'id'         => $cycle->id,
                'name'       => $cycle->name,
                'year'       => $cycle->year,
                'period'     => $cycle->period,
                'start_date' => $cycle->start_date?->toDateString(),
                'end_date'   => $cycle->end_date?->toDateString(),
            ],
            'kras'          => $kraData,
            'overall_score' => round($overallScore, 2),
        ]);
    }

    // ─── Team Appraisal ───────────────────────────────────────────────────────

    public function teamAppraisal(Request $request)
    {
        $manager = auth()->user()->employee;
        $cycle   = BscCycle::where('status', 'active')->first();

        if (!$manager || !$cycle) {
            return response()->json(['cycle' => null, 'team' => []]);
        }

        $subordinates = Employee::where('manager_id', $manager->id)
            ->where('status', 'active')
            ->with('department', 'designation')
            ->get();

        $kraIds    = $cycle->kras->pluck('id');
        $totalKras = $kraIds->count();

        $team = $subordinates->map(function (Employee $emp) use ($kraIds, $totalKras) {
            $entries = BscEntry::where('employee_id', $emp->id)
                ->whereIn('kra_id', $kraIds)
                ->get();

            return [
                'employee'        => [
                    'id'          => $emp->id,
                    'full_name'   => $emp->full_name,
                    'emp_number'  => $emp->emp_number,
                    'department'  => $emp->department?->name,
                    'designation' => $emp->designation?->name,
                ],
                'entry_count'     => $entries->count(),
                'total_kras'      => $totalKras,
                'overall_score'   => round($entries->sum('weighted_index'), 2),
                'progress'        => $totalKras > 0
                    ? round(($entries->where('status', '!=', 'draft')->count() / $totalKras) * 100)
                    : 0,
                'approved_count'  => $entries->where('status', 'approved')->count(),
                'submitted_count' => $entries->where('status', 'submitted')->count(),
            ];
        });

        return response()->json([
            'cycle' => [
                'id'     => $cycle->id,
                'name'   => $cycle->name,
                'year'   => $cycle->year,
                'period' => $cycle->period,
            ],
            'team' => $team,
        ]);
    }

    // ─── Entry CRUD ───────────────────────────────────────────────────────────

    public function showEntry(BscEntry $entry)
    {
        $entry->load(['kra.cycle', 'employee', 'appraiser']);
        return response()->json($this->formatEntry($entry));
    }

    public function updateEntry(Request $request, BscEntry $entry)
    {
        $data = $request->validate([
            'actual_achieved'   => 'nullable|numeric|min:0',
            'rating'            => 'nullable|integer|min:1|max:5',
            'employee_comment'  => 'nullable|string|max:1000',
            'appraiser_comment' => 'nullable|string|max:1000',
            'problem_areas'     => 'nullable|string|max:2000',
            'remedial_actions'  => 'nullable|string|max:2000',
            'remedial_by_when'  => 'nullable|date',
        ]);

        $entry->fill($data);
        $entry->save();
        $entry->load('kra');
        $entry->calculateWeightedIndex();

        return response()->json([
            'message' => 'Entry saved.',
            'entry'   => $this->formatEntry($entry->fresh()),
        ]);
    }

    public function submitEntry(BscEntry $entry)
    {
        if ($entry->status !== 'draft') {
            return response()->json(['message' => 'Entry is already submitted or approved.'], 422);
        }

        $entry->update(['status' => 'submitted', 'submitted_at' => now()]);
        return response()->json(['message' => 'Appraisal submitted for review.', 'status' => 'submitted']);
    }

    public function approveEntry(Request $request, BscEntry $entry)
    {
        $data = $request->validate([
            'appraiser_comment' => 'nullable|string|max:1000',
        ]);

        $employee = auth()->user()->employee;
        $entry->update(array_merge($data, [
            'status'        => 'approved',
            'approved_at'   => now(),
            'appraiser_id'  => $employee?->id,
            'appraiser_role'=> 'manager',
        ]));

        return response()->json(['message' => 'Entry approved.', 'status' => 'approved']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatKra(BscKra $kra): array
    {
        return [
            'id'               => $kra->id,
            'cycle_id'         => $kra->cycle_id,
            'perspective'      => $kra->perspective,
            'kra_name'         => $kra->kra_name,
            'objective'        => $kra->objective,
            'measure'          => $kra->measure,
            'target'           => $kra->target,
            'unit'             => $kra->unit,
            'weightage'        => $kra->weightage,
            'review_frequency' => $kra->review_frequency,
        ];
    }

    private function formatEntry(BscEntry $e): array
    {
        return [
            'id'                => $e->id,
            'kra_id'            => $e->kra_id,
            'employee_id'       => $e->employee_id,
            'actual_achieved'   => $e->actual_achieved,
            'target_percent'    => $e->target_percent,
            'rating'            => $e->rating,
            'weighted_index'    => $e->weighted_index,
            'employee_comment'  => $e->employee_comment,
            'appraiser_comment' => $e->appraiser_comment,
            'problem_areas'     => $e->problem_areas,
            'remedial_actions'  => $e->remedial_actions,
            'remedial_by_when'  => $e->remedial_by_when?->toDateString(),
            'status'            => $e->status,
            'submitted_at'      => $e->submitted_at?->toIso8601String(),
            'approved_at'       => $e->approved_at?->toIso8601String(),
        ];
    }
}
