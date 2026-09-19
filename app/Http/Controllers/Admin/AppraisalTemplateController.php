<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AppraisalTemplate, AppraisalTemplateKpi, Appraisal};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Copy a template with its KPIs.
     *
     * A scorecard is nineteen KRAs with measures, targets and weights that took
     * real thought to balance to 100%. Next cycle almost always wants the same
     * shape with a few targets moved, and until now the only way to get there
     * was to retype the lot or edit last cycle's in place — which silently
     * rewrites the template every completed appraisal was measured against.
     *
     * The copy arrives inactive. Two identical active templates in the picker is
     * how a supervisor applies the half-edited one, and the entire reason to
     * duplicate is that the copy is about to change.
     */
    public function duplicate(AppraisalTemplate $template)
    {
        $this->authorise();

        // One transaction: a template that copied without its KPIs is worse than
        // no copy at all, because it looks finished and is empty.
        $copy = DB::transaction(function () use ($template) {
            $copy = AppraisalTemplate::create([
                'name'                    => $this->copyName($template->name),
                'description'             => $template->description,
                'job_title'               => $template->job_title,
                'financial_weight'        => $template->financial_weight,
                'customer_weight'         => $template->customer_weight,
                'internal_process_weight' => $template->internal_process_weight,
                'learning_growth_weight'  => $template->learning_growth_weight,
                'is_active'               => false,
                'created_by'              => auth()->id(),
            ]);

            // sort_order is carried across rather than reassigned: the copy should
            // read in the order somebody arranged, not in id order.
            foreach ($template->kpis as $kpi) {
                $copy->kpis()->create([
                    'perspective'         => $kpi->perspective,
                    'kra_name'            => $kpi->kra_name,
                    'performance_measure' => $kpi->performance_measure,
                    'target'              => $kpi->target,
                    'weightage'           => $kpi->weightage,
                    'evidence_note'       => $kpi->evidence_note,
                    'sort_order'          => $kpi->sort_order,
                ]);
            }

            return $copy;
        });

        $count = $copy->kpis()->count();

        return redirect()->route('admin.appraisal-templates.edit', $copy)->with('success',
            "Copied “{$template->name}” with {$count} KPI(s). "
            . 'It is switched off until you activate it, so nobody applies it while you are still editing.');
    }

    /**
     * A name nobody will confuse with the original.
     *
     * Copying a copy gives “(copy 2)” rather than “(copy) (copy)”, and the
     * result is trimmed to fit a varchar(255).
     */
    private function copyName(string $name): string
    {
        // ?? $name: preg_replace returns null on failure, and a null here would
        // name every copy the same thing.
        $base = preg_replace('/ \(copy(?: \d+)?\)$/u', '', $name) ?? $name;

        for ($i = 1; $i <= 99; $i++) {
            $suffix = $i === 1 ? ' (copy)' : " (copy {$i})";
            $candidate = mb_substr($base, 0, 255 - mb_strlen($suffix)) . $suffix;

            if (! AppraisalTemplate::where('name', $candidate)->exists()) {
                return $candidate;
            }
        }

        return mb_substr($base, 0, 240) . ' (copy ' . now()->format('YmdHis') . ')';
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
