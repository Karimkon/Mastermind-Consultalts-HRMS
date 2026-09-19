<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AppraisalTemplate, AppraisalTemplateKpi, Appraisal};
use Illuminate\Http\Request;

/**
 * Admin control over how appraisals are weighted.
 *
 * The perspective split is company policy — Operations Officers run
 * 30/35/25/10, other roles differ — so an admin defines it here rather than
 * every supervisor guessing. A template also carries a starting KPI set that a
 * supervisor can apply and then adjust.
 */
class AppraisalTemplateController extends Controller
{
    private function authorise(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin', 'hr-admin']), 403,
            'Only an administrator or HR can manage appraisal templates.');
    }

    public function index()
    {
        $this->authorise();

        return view('admin.appraisal-templates.index', [
            'templates' => AppraisalTemplate::withCount('kpis')->with('creator')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorise();

        $data = $this->validateTemplate($request);
        if (is_string($data)) return back()->withInput()->with('error', $data);

        $template = AppraisalTemplate::create($data + ['created_by' => auth()->id()]);

        return redirect()->route('admin.appraisal-templates.edit', $template)
            ->with('success', 'Template created. Now add the KPIs it should start with.');
    }

    public function edit(AppraisalTemplate $template)
    {
        $this->authorise();
        $template->load('kpis');

        return view('admin.appraisal-templates.edit', compact('template'));
    }

    public function update(Request $request, AppraisalTemplate $template)
    {
        $this->authorise();

        $data = $this->validateTemplate($request);
        if (is_string($data)) return back()->withInput()->with('error', $data);

        $template->update($data);
        return back()->with('success', 'Template updated.');
    }

    public function destroy(AppraisalTemplate $template)
    {
        $this->authorise();

        // Appraisals keep working without it — the FK nulls out — but say so.
        $inUse = Appraisal::where('appraisal_template_id', $template->id)->count();
        $template->delete();

        return redirect()->route('admin.appraisal-templates.index')->with('success',
            $inUse
                ? "Template deleted. {$inUse} existing appraisal(s) keep their KPIs but lose the target split."
                : 'Template deleted.');
    }

    /**
     * Perspective weights must total 100 — that is the whole point of the
     * policy. Returns the validated array, or an error string.
     */
    private function validateTemplate(Request $request): array|string
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string|max:255',
            'job_title'               => 'nullable|string|max:255',
            'financial_weight'        => 'required|numeric|min:0|max:100',
            'customer_weight'         => 'required|numeric|min:0|max:100',
            'internal_process_weight' => 'required|numeric|min:0|max:100',
            'learning_growth_weight'  => 'required|numeric|min:0|max:100',
            'is_active'               => 'nullable|boolean',
        ]);

        $total = $data['financial_weight'] + $data['customer_weight']
               + $data['internal_process_weight'] + $data['learning_growth_weight'];

        if (abs($total - 100) > 0.01) {
            return "The four perspectives add up to {$total}%. They must total exactly 100%.";
        }

        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }

    // ── Template KPIs ────────────────────────────────────────────────────

    public function storeKpi(Request $request, AppraisalTemplate $template)
    {
        $this->authorise();

        $data = $request->validate([
            'perspective'         => 'required|in:' . implode(',', array_keys(Appraisal::PERSPECTIVES)),
            'kra_name'            => 'required|string|max:255',
            'performance_measure' => 'nullable|string|max:1000',
            'target'              => 'nullable|string|max:50',
            'weightage'           => 'required|numeric|min:0.01|max:100',
            'evidence_note'       => 'nullable|string|max:255',
        ]);

        // Same ceiling as a live card: a template must not describe more than 100%.
        $remaining = round(100 - $template->kpiWeight(), 2);
        if ($data['weightage'] > $remaining + 0.001) {
            return back()->withInput()->with('error',
                "That weight would take the template past 100%. Only {$remaining}% is left.");
        }

        $template->kpis()->create($data + [
            'sort_order' => (int) $template->kpis()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'KPI added to the template.');
    }

    /**
     * Change a KPI in place.
     *
     * There was only add and delete, so correcting a typo meant deleting the row
     * and retyping it — which also sent it to the bottom of its perspective,
     * because sort_order is assigned on create. Building a card of nineteen KRAs
     * that way reshuffles itself every time somebody fixes a target.
     */
    public function updateKpi(Request $request, AppraisalTemplate $template, AppraisalTemplateKpi $kpi)
    {
        $this->authorise();
        abort_if($kpi->appraisal_template_id !== $template->id, 404);

        $data = $request->validate([
            'perspective'         => 'required|in:' . implode(',', array_keys(Appraisal::PERSPECTIVES)),
            'kra_name'            => 'required|string|max:255',
            'performance_measure' => 'nullable|string|max:1000',
            'target'              => 'nullable|string|max:50',
            'weightage'           => 'required|numeric|min:0.01|max:100',
            'evidence_note'       => 'nullable|string|max:255',
        ]);

        // The ceiling excludes this row's own current weight, or raising a KPI
        // from 5% to 6% would be measured as though the 5% were still spent and
        // refused on a template that has room.
        $others = round($template->kpiWeight() - (float) $kpi->weightage, 2);
        $remaining = round(100 - $others, 2);

        if ($data['weightage'] > $remaining + 0.001) {
            return back()->withInput()->with('error',
                "That weight would take the template past 100%. Only {$remaining}% is available for this KPI.");
        }

        // sort_order is untouched: an edit is a correction, not a re-ordering.
        $kpi->update($data);

        return back()->with('success', 'KPI updated.');
    }

    public function destroyKpi(AppraisalTemplate $template, AppraisalTemplateKpi $kpi)
    {
        $this->authorise();
        abort_if($kpi->appraisal_template_id !== $template->id, 404);

        $kpi->delete();
        return back()->with('success', 'KPI removed from the template.');
    }
}
