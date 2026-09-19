<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Models\SalaryGrade;
use App\Models\Shift;
use Illuminate\Http\Request;

/**
 * Settings that decide what people are paid and when they are late.
 *
 * These are configuration rather than daily work, which is why they were the last
 * things to reach the phone. They are here because the moment they matter is
 * usually away from a desk: a shift changing on site, a grade being agreed in a
 * meeting, somebody asking what an allowance is actually worth.
 *
 * Reading is open to anybody signed in — a supervisor should be able to check the
 * grace period without asking. Writing is not: a salary component is an input to
 * every payslip, so creating one is HR's, and deactivating one changes next
 * month's pay for everybody who has it.
 *
 * Nothing here can be deleted. A component or grade that has been used is part of
 * the history of what somebody was paid, and removing it would make old payslips
 * unexplainable. Deactivating is the honest equivalent and is what the UI offers.
 */
class ConfigurationApiController extends Controller
{
    private function mayConfigure(Request $request): bool
    {
        // The MD is deliberately absent, matching the web: the person who signs
        // payroll off should not also be setting its inputs.
        return $request->user()->hasAnyRole(['super-admin', 'hr-admin', 'payroll-officer']);
    }

    // ── Shifts ───────────────────────────────────────────────────────────

    public function shifts(Request $request)
    {
        return response()->json([
            'data' => Shift::orderBy('start_time')->get()->map(fn (Shift $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'grace_minutes' => (int) ($s->grace_minutes ?? 0),
                // Worked out here rather than in the app: a shift that ends before
                // it starts runs through midnight, and that is a fact about the
                // shift rather than a display choice.
                'crosses_midnight' => $s->end_time !== null
                    && $s->start_time !== null
                    && $s->end_time < $s->start_time,
            ])->values(),
            'can_configure' => $this->mayConfigure($request),
        ]);
    }

    public function storeShift(Request $request)
    {
        abort_unless($this->mayConfigure($request), 403, 'Only HR or payroll can change shifts.');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            // A grace period is minutes of lateness forgiven. An hour of it is
            // almost certainly a typo for a shift boundary.
            'grace_minutes' => 'nullable|integer|min:0|max:120',
        ]);

        $shift = Shift::create([
            'name' => $data['name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'grace_minutes' => $data['grace_minutes'] ?? 0,
        ]);

        return response()->json(['data' => ['id' => $shift->id]], 201);
    }

    // ── Salary grades ────────────────────────────────────────────────────

    public function grades(Request $request)
    {
        return response()->json([
            'data' => SalaryGrade::orderBy('basic_min')->get()->map(fn (SalaryGrade $g) => [
                'id' => $g->id,
                'grade' => $g->grade,
                'label' => $g->label,
                'basic_min' => (float) $g->basic_min,
                'basic_max' => (float) $g->basic_max,
            ])->values(),
            'can_configure' => $this->mayConfigure($request),
        ]);
    }

    public function storeGrade(Request $request)
    {
        abort_unless($this->mayConfigure($request), 403, 'Only HR or payroll can change salary grades.');

        $data = $request->validate([
            'grade' => 'required|string|max:20',
            // What the grade is called in conversation: Junior, Associate, Lead.
            'label' => 'nullable|string|max:255',
            'basic_min' => 'required|numeric|min:0',
            // A band whose ceiling is below its floor cannot contain anybody, and
            // the failure would only show when somebody was placed in it.
            'basic_max' => 'required|numeric|gte:basic_min',
        ]);

        $grade = SalaryGrade::create([
            'grade' => $data['grade'],
            // Not null in the database, so an absent label is an empty string
            // rather than a failed insert.
            'label' => $data['label'] ?? '',
            'basic_min' => $data['basic_min'],
            'basic_max' => $data['basic_max'],
        ]);

        return response()->json(['data' => ['id' => $grade->id]], 201);
    }

    // ── Salary components ────────────────────────────────────────────────

    public function components(Request $request)
    {
        return response()->json([
            'data' => SalaryComponent::orderBy('type')->orderBy('name')->get()
                ->map(fn (SalaryComponent $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'type' => $c->type,
                    'is_taxable' => (bool) $c->is_taxable,
                    'is_fixed' => (bool) $c->is_fixed,
                    'amount' => (float) $c->amount,
                    'percentage' => (float) $c->percentage,
                    'is_active' => (bool) $c->is_active,
                    // The two the payroll service recognises by name. Saying so
                    // stops somebody editing them casually.
                    'is_statutory' => in_array($c->code, ['NSSF_EMP', 'NSSF_CO'], true),
                ])->values(),
            'can_configure' => $this->mayConfigure($request),
        ]);
    }

    public function storeComponent(Request $request)
    {
        abort_unless($this->mayConfigure($request), 403, 'Only HR or payroll can change salary components.');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            // Required and unique because PayrollService identifies the statutory
            // components by code and by nothing else.
            'code' => 'required|string|max:30|unique:salary_components,code',
            'type' => 'required|in:allowance,deduction',
            'is_taxable' => 'nullable|boolean',
            'is_fixed' => 'nullable|boolean',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $component = SalaryComponent::create([
            'name' => $data['name'],
            'code' => mb_strtoupper($data['code']),
            'type' => $data['type'],
            'is_taxable' => $request->boolean('is_taxable'),
            'is_fixed' => $request->boolean('is_fixed'),
            'amount' => $data['amount'] ?? 0,
            'percentage' => $data['percentage'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json(['data' => ['id' => $component->id]], 201);
    }

    /**
     * Turn a component on or off.
     *
     * The only mutation offered on an existing component, and the reason is the
     * payslip: changing the rate of something people are already receiving
     * rewrites next month's pay for everybody who has it, and that belongs in
     * front of the full list in a browser.
     *
     * The statutory two cannot be switched off from here at all. Disabling
     * `NSSF_EMP` stops a legally required deduction, and doing it from a phone by
     * accident is not a risk worth carrying for the convenience.
     */
    public function setComponentActive(Request $request, SalaryComponent $component)
    {
        abort_unless($this->mayConfigure($request), 403, 'Only HR or payroll can change salary components.');

        if (in_array($component->code, ['NSSF_EMP', 'NSSF_CO'], true)) {
            return response()->json([
                'message' => 'NSSF is statutory and cannot be switched off from the app.',
            ], 422);
        }

        $component->update(['is_active' => $request->boolean('is_active')]);

        return response()->json([
            'data' => ['id' => $component->id, 'is_active' => (bool) $component->is_active],
        ]);
    }
}
