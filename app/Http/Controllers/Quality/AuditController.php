<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\QualityAudit;
use App\Models\QualityNonconformity;
use App\Models\QualityStandard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    /** Audit item result -> score contribution. NA is excluded from the average. */
    private const ITEM_SCORE = [
        'conform' => 100,
        'observation' => 85,
        'minor_nc' => 50,
        'major_nc' => 0,
    ];

    public function index()
    {
        $audits = QualityAudit::with('auditor', 'department')
            ->withCount('items')
            ->latest()->paginate(20);

        return view('quality.audits.index', compact('audits'));
    }

    public function create()
    {
        $standards = QualityStandard::where('is_active', true)->orderBy('hr_function')->get();
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $auditors = User::orderBy('name')->get(['id', 'name']);

        return view('quality.audits.create', compact('standards', 'departments', 'auditors'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'scope' => 'required|string|max:50',
            'type' => 'required|in:internal,external,process',
            'auditor_id' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'planned_date' => 'nullable|date',
        ]);

        $audit = QualityAudit::create($data + [
            'reference' => QualityAudit::nextReference(),
            'status' => 'planned',
        ]);

        // Build the checklist from the active standards in scope.
        $standards = QualityStandard::where('is_active', true)
            ->when($data['scope'] !== 'all', fn ($q) => $q->where('hr_function', $data['scope']))
            ->get();

        foreach ($standards as $std) {
            $audit->items()->create([
                'quality_standard_id' => $std->id,
                'question' => 'Does the organisation conform to: ' . $std->title . '?',
            ]);
        }

        return redirect()->route('quality.audits.show', $audit)
            ->with('success', "Audit {$audit->reference} created with {$standards->count()} checklist item(s).");
    }

    public function show(QualityAudit $audit)
    {
        $audit->load(['items.standard', 'auditor', 'department']);

        return view('quality.audits.show', compact('audit'));
    }

    /** Save the conducted results for each item (and move to in_progress). */
    public function update(Request $request, QualityAudit $audit)
    {
        $request->validate(['items' => 'array']);

        foreach ($request->input('items', []) as $itemId => $fields) {
            $item = $audit->items()->whereKey($itemId)->first();
            if (! $item) continue;
            $item->update([
                'result' => $fields['result'] ?? null,
                'notes' => $fields['notes'] ?? null,
                'evidence' => $fields['evidence'] ?? null,
            ]);
        }

        if ($audit->status === 'planned') {
            $audit->update(['status' => 'in_progress', 'started_at' => now()]);
        }

        return back()->with('success', 'Audit progress saved.');
    }

    /** Close the audit: score it and raise non-conformities from the findings. */
    public function complete(Request $request, QualityAudit $audit)
    {
        $audit->load('items.standard');

        $scored = $audit->items->whereNotNull('result')->where('result', '!=', 'na');
        $score = $scored->count()
            ? round($scored->avg(fn ($i) => self::ITEM_SCORE[$i->result] ?? 0), 2)
            : null;

        $raised = 0;
        foreach ($audit->items as $item) {
            if (! in_array($item->result, ['minor_nc', 'major_nc'], true)) continue;

            // Don't duplicate a finding already raised for this audit item.
            $exists = QualityNonconformity::where('quality_audit_id', $audit->id)
                ->where('subject_label', $item->standard?->code)
                ->whereIn('status', QualityNonconformity::OPEN_STATES)
                ->exists();
            if ($exists) continue;

            QualityNonconformity::create([
                'reference' => QualityNonconformity::nextReference(),
                'quality_audit_id' => $audit->id,
                'hr_function' => $item->standard?->hr_function ?? ($audit->scope === 'all' ? 'compliance' : $audit->scope),
                'subject_label' => $item->standard?->code,
                'title' => ($item->result === 'major_nc' ? 'Major NC: ' : 'Minor NC: ') . ($item->standard?->title ?? $item->question),
                'description' => $item->notes ?: $item->question,
                'severity' => $item->result === 'major_nc' ? 'high' : 'medium',
                'source' => 'audit',
                'status' => 'open',
                'raised_by' => $request->user()?->id,
                'detected_at' => now(),
            ]);
            $raised++;
        }

        $audit->update([
            'status' => 'completed',
            'completed_at' => now(),
            'score' => $score,
            'summary' => $request->input('summary'),
        ]);

        return redirect()->route('quality.audits.show', $audit)
            ->with('success', "Audit closed. Score " . ($score ?? 'n/a') . "%. {$raised} non-conformity(ies) raised.");
    }
}
