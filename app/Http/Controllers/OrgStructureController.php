<?php
namespace App\Http\Controllers;

use App\Models\{Employee, OrgPosition};
use Illuminate\Http\Request;

class OrgStructureController extends Controller
{
    /**
     * The chain of command, top down.
     *
     * Client sites are collapsed to a headcount by default rather than drawn as
     * 439 boxes - the chart is there to show who answers to whom, and a wall of
     * loaders at Roofings buries that. Opening a site lists its staff.
     */
    public function index(Request $request)
    {
        $root = OrgPosition::whereNull('client_id')->whereNull('parent_id')
            ->with(['descendants', 'employees.user'])
            ->orderBy('sort_order')
            ->first();

        // Headcount per position, gathered in one query rather than one per box.
        $headcount = Employee::selectRaw('org_position_id, COUNT(*) as total')
            ->whereNotNull('org_position_id')
            ->groupBy('org_position_id')
            ->pluck('total', 'org_position_id');

        $unassigned = Employee::whereNull('manager_id')->count();

        // Only Head Office is counted here. Staff placed at a client site report
        // to their account manager and are not expected to hold a post on
        // Mastermind's own chart, so counting all of them read as "1,234 people
        // are missing" when nothing was actually wrong.
        $noPosition = Employee::whereNull('org_position_id')
            ->whereHas('department', fn($d) => $d->where('name', 'Mastermind Head Office'))
            ->count();

        return view('org-structure.index', compact(
            'root', 'headcount', 'unassigned', 'noPosition'
        ));
    }

    /** One position: who sits in it, who it answers to, and what reports to it. */
    public function show(OrgPosition $position)
    {
        $position->load(['children', 'client', 'employees.user', 'employees.department',
                         'employees.designation']);

        return view('org-structure.show', [
            'position' => $position,
            'chain'    => array_reverse($position->chainOfCommand()),
        ]);
    }
}
