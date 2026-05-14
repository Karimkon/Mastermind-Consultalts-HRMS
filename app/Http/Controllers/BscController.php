<?php
namespace App\Http\Controllers;

use App\Models\BscCycle;
use App\Models\BscEntry;
use App\Models\BscKra;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BscController extends Controller
{
    // =========================================================
    // CYCLES
    // =========================================================

    public function index()
    {
        $cycles = BscCycle::withCount('kras')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->paginate(15);

        return view('bsc.cycles.index', compact('cycles'));
    }

    public function createCycle()
    {
        return view('bsc.cycles.create');
    }

    public function storeCycle(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'year'       => 'required|integer|min:2020|max:2050',
            'period'     => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        $data['status']     = 'draft';
        $data['created_by'] = auth()->id();

        $cycle = BscCycle::create($data);

        return redirect()
            ->route('bsc.cycles.show', $cycle)
            ->with('success', "BSC Cycle '{$cycle->name}' created successfully.");
    }

    public function showCycle(BscCycle $cycle)
    {
        $cycle->load(['kras' => function ($q) {
            $q->orderBy('perspective')->orderBy('sort_order');
        }]);

        $weights = $cycle->perspectiveWeights;

        // Build perspective summary
        $perspectives = [
            'financial'        => ['label' => 'Financial',                'color' => 'blue',   'weight' => $weights['financial'],        'kras' => []],
            'customer'         => ['label' => 'Customer',                 'color' => 'green',  'weight' => $weights['customer'],          'kras' => []],
            'internal_process' => ['label' => 'Internal Business Process','color' => 'purple', 'weight' => $weights['internal_process'],  'kras' => []],
            'learning_growth'  => ['label' => 'Learning & Growth',        'color' => 'orange', 'weight' => $weights['learning_growth'],   'kras' => []],
        ];

        foreach ($cycle->kras as $kra) {
            if (isset($perspectives[$kra->perspective])) {
                $perspectives[$kra->perspective]['kras'][] = $kra;
            }
        }

        // Compute total weightage per perspective
        foreach ($perspectives as $key => &$p) {
            $p['total_weightage'] = collect($p['kras'])->sum('weightage');
            $p['kra_count']       = count($p['kras']);
        }
        unset($p);

        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();

        return view('bsc.cycles.show', compact('cycle', 'perspectives', 'employees'));
    }

    public function editCycle(BscCycle $cycle)
    {
        return view('bsc.cycles.edit', compact('cycle'));
    }

    public function updateCycle(Request $request, BscCycle $cycle)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'year'       => 'required|integer|min:2020|max:2050',
            'period'     => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        $cycle->update($data);

        return redirect()
            ->route('bsc.cycles.show', $cycle)
            ->with('success', 'BSC Cycle updated.');
    }

    public function activateCycle(BscCycle $cycle)
    {
        // Deactivate all others first
        BscCycle::where('status', 'active')->update(['status' => 'draft']);
        $cycle->update(['status' => 'active']);

        return back()->with('success', "Cycle '{$cycle->name}' is now active.");
    }

    public function closeCycle(BscCycle $cycle)
    {
        $cycle->update(['status' => 'closed']);

        return back()->with('success', "Cycle '{$cycle->name}' has been closed.");
    }

    // =========================================================
    // KRAs
    // =========================================================

    public function createKra(BscCycle $cycle)
    {
        return view('bsc.kras.create', compact('cycle'));
    }

    public function storeKra(Request $request, BscCycle $cycle)
    {
        $data = $request->validate([
            'perspective'      => 'required|in:financial,customer,internal_process,learning_growth',
            'kra_name'         => 'required|string|max:255',
            'objective'        => 'nullable|string|max:500',
            'measure'          => 'nullable|string|max:255',
            'target'           => 'required|numeric|min:0',
            'unit'             => 'nullable|string|max:50',
            'weightage'        => 'required|numeric|min:0|max:100',
            'review_frequency' => 'required|in:monthly,quarterly,annual',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        $data['cycle_id']    = $cycle->id;
        $data['sort_order']  = $data['sort_order'] ?? 0;

        BscKra::create($data);

        return redirect()
            ->route('bsc.cycles.show', $cycle)
            ->with('success', 'KRA added successfully.');
    }

    public function editKra(BscKra $kra)
    {
        $kra->load('cycle');
        return view('bsc.kras.edit', compact('kra'));
    }

    public function updateKra(Request $request, BscKra $kra)
    {
        $data = $request->validate([
            'perspective'      => 'required|in:financial,customer,internal_process,learning_growth',
            'kra_name'         => 'required|string|max:255',
            'objective'        => 'nullable|string|max:500',
            'measure'          => 'nullable|string|max:255',
            'target'           => 'required|numeric|min:0',
            'unit'             => 'nullable|string|max:50',
            'weightage'        => 'required|numeric|min:0|max:100',
            'review_frequency' => 'required|in:monthly,quarterly,annual',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        $kra->update($data);

        return redirect()
            ->route('bsc.cycles.show', $kra->cycle_id)
            ->with('success', 'KRA updated.');
    }

    public function destroyKra(BscKra $kra)
    {
        $cycleId = $kra->cycle_id;
        $kra->delete();

        return redirect()
            ->route('bsc.cycles.show', $cycleId)
            ->with('success', 'KRA deleted.');
    }

    // =========================================================
    // APPRAISALS — Employee (self)
    // =========================================================

    public function myAppraisal()
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return view('bsc.my-appraisal', ['cycle' => null, 'perspectives' => [], 'overallScore' => 0]);
        }

        $cycle = BscCycle::where('status', 'active')->first();

        if (!$cycle) {
            return view('bsc.my-appraisal', ['cycle' => null, 'perspectives' => [], 'overallScore' => 0]);
        }

        $entries = BscEntry::with('kra')
            ->where('employee_id', $employee->id)
            ->whereHas('kra', fn($q) => $q->where('cycle_id', $cycle->id))
            ->get()
            ->keyBy('kra_id');

        $cycle->load(['kras' => fn($q) => $q->orderBy('perspective')->orderBy('sort_order')]);

        $weights      = $cycle->perspectiveWeights;
        $perspectives = $this->buildPerspectivesWithEntries($cycle, $weights, $entries);
        $overallScore = $entries->sum('weighted_index');

        return view('bsc.my-appraisal', compact('cycle', 'perspectives', 'overallScore', 'employee'));
    }

    // =========================================================
    // APPRAISALS — Team (AM / Manager)
    // =========================================================

    public function teamAppraisal(Request $request)
    {
        $manager  = auth()->user()->employee;
        $cycle    = BscCycle::where('status', 'active')->first();
        $statusFilter = $request->get('status');

        if (!$manager || !$cycle) {
            return view('bsc.team-appraisal', ['cycle' => $cycle, 'team' => collect(), 'statusFilter' => $statusFilter]);
        }

        $subordinates = Employee::where('manager_id', $manager->id)
            ->where('status', 'active')
            ->with('department', 'designation')
            ->get();

        $kraIds = $cycle->kras->pluck('id');
        $totalKras = $kraIds->count();

        $team = $subordinates->map(function (Employee $emp) use ($kraIds, $totalKras, $statusFilter) {
            $query = BscEntry::where('employee_id', $emp->id)
                ->whereIn('kra_id', $kraIds);

            if ($statusFilter) {
                $query->where('status', $statusFilter);
            }

            $entries = $query->get();

            return [
                'employee'       => $emp,
                'entries'        => $entries,
                'entry_count'    => $entries->count(),
                'total_kras'     => $totalKras,
                'overall_score'  => round($entries->sum('weighted_index'), 2),
                'progress'       => $totalKras > 0 ? round(($entries->where('status', '!=', 'draft')->count() / $totalKras) * 100) : 0,
                'approved_count' => $entries->where('status', 'approved')->count(),
                'submitted_count'=> $entries->where('status', 'submitted')->count(),
            ];
        });

        return view('bsc.team-appraisal', compact('cycle', 'team', 'statusFilter'));
    }

    // =========================================================
    // ENTRY — View / Edit / Submit / Approve
    // =========================================================

    public function showEntry(BscEntry $entry)
    {
        $entry->load(['kra.cycle', 'employee', 'appraiser']);
        return view('bsc.entry-show', compact('entry'));
    }

    public function editEntry(BscEntry $entry)
    {
        $entry->load(['kra.cycle', 'employee', 'appraiser']);
        return view('bsc.entry-edit', compact('entry'));
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

        return back()->with('success', 'Entry saved.');
    }

    public function submitEntry(BscEntry $entry)
    {
        $entry->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Appraisal submitted for review.');
    }

    public function approveEntry(BscEntry $entry)
    {
        $user = auth()->user();

        // Only roles that can approve
        if (!$user->hasAnyRole(['super-admin', 'hr-admin', 'manager', 'account-manager'])) {
            return back()->with('error', 'You do not have permission to approve entries.');
        }

        $entry->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Entry approved.');
    }

    // =========================================================
    // INITIALIZE APPRAISAL (Admin bulk-create entries)
    // =========================================================

    public function initializeAppraisal(Request $request, BscCycle $cycle)
    {
        $request->validate([
            'employee_ids'   => 'required_without:all_employees',
            'employee_ids.*' => 'exists:employees,id',
            'all_employees'  => 'nullable|boolean',
            'appraiser_role' => 'required|in:admin,hr,account_manager,self',
        ]);

        $kras = $cycle->kras;

        if ($kras->isEmpty()) {
            return back()->with('error', 'This cycle has no KRAs defined. Add KRAs first.');
        }

        if ($request->boolean('all_employees')) {
            $employees = Employee::where('status', 'active')->pluck('id');
        } else {
            $employees = collect($request->input('employee_ids', []));
        }

        $appraiserRole = $request->input('appraiser_role');
        $appraiserId   = auth()->user()->employee?->id;
        $created       = 0;

        DB::transaction(function () use ($kras, $employees, $appraiserRole, $appraiserId, &$created) {
            foreach ($employees as $empId) {
                foreach ($kras as $kra) {
                    $exists = BscEntry::where('kra_id', $kra->id)
                        ->where('employee_id', $empId)
                        ->where('appraiser_role', $appraiserRole)
                        ->exists();

                    if (!$exists) {
                        BscEntry::create([
                            'kra_id'         => $kra->id,
                            'employee_id'    => $empId,
                            'appraiser_id'   => $appraiserId,
                            'appraiser_role' => $appraiserRole,
                            'status'         => 'draft',
                        ]);
                        $created++;
                    }
                }
            }
        });

        return back()->with('success', "{$created} appraisal entries initialized successfully.");
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    private function buildPerspectivesWithEntries(BscCycle $cycle, array $weights, $entries): array
    {
        $perspectives = [
            'financial'        => ['label' => 'Financial',                'color' => 'blue',   'weight' => $weights['financial'],        'kras' => []],
            'customer'         => ['label' => 'Customer',                 'color' => 'green',  'weight' => $weights['customer'],          'kras' => []],
            'internal_process' => ['label' => 'Internal Business Process','color' => 'purple', 'weight' => $weights['internal_process'],  'kras' => []],
            'learning_growth'  => ['label' => 'Learning & Growth',        'color' => 'orange', 'weight' => $weights['learning_growth'],   'kras' => []],
        ];

        foreach ($cycle->kras as $kra) {
            if (isset($perspectives[$kra->perspective])) {
                $perspectives[$kra->perspective]['kras'][] = [
                    'kra'   => $kra,
                    'entry' => $entries->get($kra->id),
                ];
            }
        }

        return $perspectives;
    }
}
