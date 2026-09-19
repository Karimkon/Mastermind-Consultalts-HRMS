<?php
namespace App\Http\Controllers;

use App\Models\{Appraisal, AppraisalAction, AppraisalAttachment, AppraisalKpi,
                AppraisalTemplate, Client, Employee, Notification, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Individual Balanced Score Card.
 *
 * Routing, as the business described it:
 *   supervisor sets the KPIs and weights, sends it to an appraiser (usually the
 *   account manager who runs that site) → the appraiser scores it and sends it
 *   back to whoever started it, or to someone else they nominate such as HR or
 *   the MD → that person confirms → the employee self-appraises and signs.
 *
 * Weights can never total more than 100%: that is enforced when a KPI is saved
 * and again before the card leaves the supervisor.
 */
class AppraisalController extends Controller
{
    private const SETUP_ROLES = ['super-admin', 'hr-admin', 'manager', 'account-manager'];

    private function canSetUp(): bool
    {
        return auth()->user()->hasAnyRole(self::SETUP_ROLES);
    }

    /** Appraisals this user is allowed to see. */
    private function visibleQuery()
    {
        $user = auth()->user();
        $q = Appraisal::with(['employee.user', 'client', 'initiator', 'appraiser']);

        if ($user->hasAnyRole(['super-admin', 'hr-admin'])) return $q;

        // Everyone else sees the cards they started, hold, must confirm, or own.
        return $q->where(function ($w) use ($user) {
            $w->where('initiated_by', $user->id)
              ->orWhere('appraiser_id', $user->id)
              ->orWhere('return_to_id', $user->id)
              ->orWhereHas('employee', fn($e) => $e->where('user_id', $user->id));
        });
    }

    private function authoriseView(Appraisal $appraisal): void
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['super-admin', 'hr-admin'])) return;

        $allowed = in_array($user->id, array_filter([
            $appraisal->initiated_by, $appraisal->appraiser_id,
            $appraisal->return_to_id, $appraisal->employee?->user_id,
        ]), true);

        abort_unless($allowed, 403, 'This appraisal is not yours to view.');
    }

    private function notify(?int $userId, string $title, string $body, Appraisal $a): void
    {
        if (!$userId) return;

        Notification::create([
            'user_id' => $userId,
            'type'    => 'appraisal',
            'title'   => $title,
            'body'    => $body,
            'data'    => ['appraisal_id' => $a->id, 'url' => route('appraisals.show', $a)],
        ]);
    }

    // ── Listing ──────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $appraisals = $this->visibleQuery()
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->latest()->paginate(20)->withQueryString();

        // The cards sitting with me right now — the actionable ones.
        $mine = $this->visibleQuery()->get()
            ->filter(fn($a) => $a->currentHolderId() === auth()->id())
            ->values();

        return view('appraisals.index', [
            'appraisals' => $appraisals,
            'mine'       => $mine,
            'canSetUp'   => $this->canSetUp(),
        ]);
    }

    // ── Create ───────────────────────────────────────────────────────────

    public function create()
    {
        abort_unless($this->canSetUp(), 403, 'Only a supervisor, HR or an admin can start an appraisal.');

        return view('appraisals.create', [
            'employees' => Employee::with('user', 'department')->where('status', 'active')
                            ->orderBy('first_name')->get(),
            'clients'   => Client::orderBy('company_name')->get(),
            'appraisers'=> $this->appraiserChoices(),
            'templates' => AppraisalTemplate::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Staff who can be asked to score a card. */
    private function appraiserChoices()
    {
        return User::role(['account-manager', 'manager', 'hr-admin', 'super-admin', 'md', 'client'])
            ->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        abort_unless($this->canSetUp(), 403);

        $data = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:internal,external',
            'client_id'    => 'nullable|exists:clients,id',
            'year'         => 'required|integer|min:2020|max:2100',
            'period'       => 'nullable|string|max:20',
            'review_from'  => 'nullable|date',
            'review_to'    => 'nullable|date|after_or_equal:review_from',
            'appraiser_id' => 'nullable|exists:users,id',
            'appraisal_template_id' => 'nullable|exists:appraisal_templates,id',
        ]);

        // An external card is the client scoring staff on their site, so it
        // only makes sense against a client.
        if ($data['type'] === 'external' && empty($data['client_id'])) {
            return back()->withInput()->with('error',
                'An external appraisal is done by a client, so pick which client it is for.');
        }

        $appraisal = Appraisal::create($data + [
            'initiated_by' => auth()->id(),
            'return_to_id' => auth()->id(),
            'status'       => 'draft',
        ]);

        $appraisal->log('created', 'draft', 'Appraisal created.');

        // Applying a template copies its KPIs in as a starting point. They are
        // ordinary rows from here on, so the supervisor can tune any of them.
        $copied = 0;
        if ($template = AppraisalTemplate::with('kpis')->find($data['appraisal_template_id'] ?? null)) {
            foreach ($template->kpis as $i => $tk) {
                $appraisal->kpis()->create([
                    'perspective'         => $tk->perspective,
                    'kra_name'            => $tk->kra_name,
                    'performance_measure' => $tk->performance_measure,
                    'target'              => $tk->target,
                    'weightage'           => $tk->weightage,
                    'evidence_note'       => $tk->evidence_note,
                    'sort_order'          => $i + 1,
                ]);
                $copied++;
            }
            if ($copied) $appraisal->log('template_applied', 'draft', "Applied template: {$template->name}");
        }

        return redirect()->route('appraisals.edit', $appraisal)->with('success',
            $copied
                ? "Appraisal created with {$copied} KPI(s) from the template. Adjust them as needed."
                : 'Appraisal created. Now add the KPIs and their weights.');
    }

    // ── Setting the KPIs ─────────────────────────────────────────────────

    public function edit(Appraisal $appraisal)
    {
        $this->authoriseView($appraisal);

        // The supervisor edits while it is a draft. Admin and HR can also open
        // it later to correct a weight — see updateKpi() for the same rule.
        $isAdmin = auth()->user()->hasAnyRole(['super-admin', 'hr-admin']);
        abort_unless($appraisal->status === 'draft' || $isAdmin, 403,
            'The KPIs are locked once the appraisal has been sent out.');
        abort_unless(auth()->id() === $appraisal->initiated_by || $isAdmin, 403);

        $appraisal->load('kpis', 'employee.user', 'template');

        return view('appraisals.edit', [
            'appraisal'  => $appraisal,
            'appraisers' => $this->appraiserChoices(),
            'isAdmin'    => $isAdmin,
        ]);
    }

    public function storeKpi(Request $request, Appraisal $appraisal)
    {
        abort_unless($appraisal->status === 'draft', 403, 'KPIs are locked.');

        $data = $request->validate([
            'perspective'         => 'required|in:' . implode(',', array_keys(Appraisal::PERSPECTIVES)),
            'kra_name'            => 'required|string|max:255',
            'performance_measure' => 'nullable|string|max:1000',
            'target'              => 'nullable|string|max:50',
            'weightage'           => 'required|numeric|min:0.01|max:100',
            'evidence_note'       => 'nullable|string|max:255',
        ]);

        // The whole card can never be worth more than 100%.
        $remaining = $appraisal->remainingWeight();
        if ($data['weightage'] > $remaining + 0.001) {
            return back()->withInput()->with('error',
                "That weight would take the card past 100%. Only {$remaining}% is left to allocate.");
        }

        $appraisal->kpis()->create($data + [
            'sort_order' => (int) $appraisal->kpis()->max('sort_order') + 1,
        ]);

        return back()->with('success',
            "KPI added. {$appraisal->fresh()->remainingWeight()}% of the weighting still to allocate.");
    }

    public function updateKpi(Request $request, Appraisal $appraisal, AppraisalKpi $kpi)
    {
        abort_if($kpi->appraisal_id !== $appraisal->id, 404);

        // Normally the KPI set freezes once the card is sent, so an appraiser
        // cannot be scored against moving goalposts. Admin and HR keep an
        // override for genuine corrections, and every change is logged.
        $isAdmin = auth()->user()->hasAnyRole(['super-admin', 'hr-admin']);
        abort_unless($appraisal->status === 'draft' || $isAdmin, 403,
            'KPIs are locked once the appraisal has been sent. Ask HR or an administrator to adjust them.');

        $data = $request->validate([
            'kra_name'            => 'required|string|max:255',
            'performance_measure' => 'nullable|string|max:1000',
            'target'              => 'nullable|string|max:50',
            'weightage'           => 'required|numeric|min:0.01|max:100',
            'evidence_note'       => 'nullable|string|max:255',
        ]);

        // Compare against the total excluding this row, so editing in place works.
        $others = $appraisal->kpis()->where('id', '!=', $kpi->id)->sum('weightage');
        if ($others + $data['weightage'] > 100.001) {
            $left = round(100 - $others, 2);
            return back()->with('error', "That weight would take the card past 100%. This KPI can be at most {$left}%.");
        }

        $wasWeight = $kpi->weightage;
        $kpi->update($data);

        // A weight change alters the score, so re-derive it and leave a trail.
        if (abs($wasWeight - $kpi->weightage) > 0.001) {
            $kpi->recalculate();
            $appraisal->recalculate();
            if ($appraisal->status !== 'draft') {
                $appraisal->log('weight_adjusted', $appraisal->status,
                    "{$kpi->kra_name}: weight changed from {$wasWeight}% to {$kpi->weightage}% by " . auth()->user()->name);
            }
        }

        return back()->with('success', 'KPI updated.');
    }

    public function destroyKpi(Appraisal $appraisal, AppraisalKpi $kpi)
    {
        abort_if($kpi->appraisal_id !== $appraisal->id, 404);
        abort_unless($appraisal->status === 'draft', 403, 'KPIs are locked.');

        $kpi->delete();
        return back()->with('success', 'KPI removed.');
    }

    /** Supervisor sends the completed KPI set to the appraiser. */
    public function send(Request $request, Appraisal $appraisal)
    {
        abort_unless($appraisal->status === 'draft', 403);
        abort_unless(auth()->id() === $appraisal->initiated_by
            || auth()->user()->hasAnyRole(['super-admin', 'hr-admin']), 403);

        $data = $request->validate([
            'appraiser_id' => 'required|exists:users,id',
            'comment'      => 'nullable|string|max:500',
        ]);

        if ($appraisal->kpis()->count() === 0) {
            return back()->with('error', 'Add at least one KPI before sending this out.');
        }

        // The card must be worth exactly 100% before anyone scores it.
        if (!$appraisal->weightIsComplete()) {
            $total = $appraisal->totalWeight();
            return back()->with('error',
                "The weights add up to {$total}%, not 100%. Adjust them before sending.");
        }

        $appraisal->update([
            'appraiser_id' => $data['appraiser_id'],
            'status'       => 'with_appraiser',
        ]);
        $appraisal->log('sent_to_appraiser', 'with_appraiser', $data['comment'] ?? null);

        $this->notify($data['appraiser_id'], 'Appraisal to complete',
            "{$appraisal->initiator?->name} has asked you to appraise {$appraisal->employee?->full_name} — {$appraisal->title}.",
            $appraisal);

        return redirect()->route('appraisals.show', $appraisal)
            ->with('success', 'Sent to ' . User::find($data['appraiser_id'])?->name . ' for scoring.');
    }

    // ── Viewing / scoring ────────────────────────────────────────────────

    public function show(Appraisal $appraisal)
    {
        $this->authoriseView($appraisal);
        $appraisal->load(['kpis.attachments', 'actions', 'attachments.uploader',
                          'history.user', 'employee.user', 'employee.department',
                          'employee.designation', 'client', 'initiator', 'appraiser', 'returnTo']);

        return view('appraisals.show', [
            'appraisal'  => $appraisal,
            'grouped'    => $appraisal->kpisByPerspective(),
            'canScore'   => $appraisal->status === 'with_appraiser' && auth()->id() === $appraisal->appraiser_id,
            'canConfirm' => $appraisal->status === 'with_manager'
                            && auth()->id() === ($appraisal->return_to_id ?? $appraisal->initiated_by),
            'canSelf'    => $appraisal->status === 'with_employee'
                            && auth()->id() === $appraisal->employee?->user_id,
            'returnees'  => $this->appraiserChoices(),
        ]);
    }

    /** The appraiser fills in actuals, ratings and evidence notes. */
    public function score(Request $request, Appraisal $appraisal)
    {
        abort_unless($appraisal->status === 'with_appraiser' && auth()->id() === $appraisal->appraiser_id,
            403, 'This appraisal is not with you for scoring.');

        $rows = $request->validate([
            'kpi'                    => 'required|array',
            'kpi.*.actual_achieved'  => 'nullable|string|max:50',
            'kpi.*.rating'           => 'nullable|integer|min:1|max:5',
            'kpi.*.evidence_note'    => 'nullable|string|max:255',
        ])['kpi'];

        foreach ($appraisal->kpis as $kpi) {
            if (!isset($rows[$kpi->id])) continue;
            $kpi->fill([
                'actual_achieved' => $rows[$kpi->id]['actual_achieved'] ?? null,
                'rating'          => $rows[$kpi->id]['rating'] ?? null,
                'evidence_note'   => $rows[$kpi->id]['evidence_note'] ?? null,
            ]);
            $kpi->recalculate();
        }

        $appraisal->recalculate();
        return back()->with('success', 'Scores saved.');
    }

    /** Appraiser returns the card — to the initiator, or someone they nominate. */
    public function returnToManager(Request $request, Appraisal $appraisal)
    {
        abort_unless($appraisal->status === 'with_appraiser' && auth()->id() === $appraisal->appraiser_id, 403);

        $data = $request->validate([
            'return_to_id' => 'required|exists:users,id',
            'comment'      => 'nullable|string|max:500',
        ]);

        $unrated = $appraisal->kpis()->whereNull('rating')->count();
        if ($unrated > 0) {
            return back()->with('error', "{$unrated} KPI(s) still have no rating. Score them all before returning this.");
        }

        $appraisal->recalculate();
        $appraisal->update(['return_to_id' => $data['return_to_id'], 'status' => 'with_manager']);
        $appraisal->log('returned', 'with_manager', $data['comment'] ?? null);

        $this->notify($data['return_to_id'], 'Appraisal ready for your confirmation',
            "{$appraisal->appraiser?->name} has completed the appraisal for {$appraisal->employee?->full_name}.",
            $appraisal);

        return back()->with('success', 'Returned to ' . User::find($data['return_to_id'])?->name . '.');
    }

    /** Line manager confirms, which passes it to the employee. */
    public function confirm(Request $request, Appraisal $appraisal)
    {
        abort_unless($appraisal->status === 'with_manager'
            && auth()->id() === ($appraisal->return_to_id ?? $appraisal->initiated_by), 403);

        $data = $request->validate(['manager_comment' => 'nullable|string|max:2000']);

        $appraisal->update([
            'manager_comment'   => $data['manager_comment'] ?? null,
            'manager_signed_at' => now(),
            'status'            => 'with_employee',
        ]);
        $appraisal->log('confirmed', 'with_employee', $data['manager_comment'] ?? null);

        $this->notify($appraisal->employee?->user_id, 'Your appraisal is ready',
            "Your appraisal for {$appraisal->title} has been confirmed. Please add your own comments and sign it off.",
            $appraisal);

        return back()->with('success', 'Confirmed and sent to the employee for self-appraisal.');
    }

    /** Send it back to the appraiser for another look. */
    public function sendBack(Request $request, Appraisal $appraisal)
    {
        abort_unless($appraisal->status === 'with_manager'
            && auth()->id() === ($appraisal->return_to_id ?? $appraisal->initiated_by), 403);

        $data = $request->validate(['comment' => 'required|string|max:500']);

        $appraisal->update(['status' => 'with_appraiser']);
        $appraisal->log('sent_back', 'with_appraiser', $data['comment']);

        $this->notify($appraisal->appraiser_id, 'Appraisal sent back to you',
            "The appraisal for {$appraisal->employee?->full_name} needs another look: {$data['comment']}",
            $appraisal);

        return back()->with('success', 'Sent back to the appraiser.');
    }

    /** Employee's own comments, which close the card. */
    public function selfAppraise(Request $request, Appraisal $appraisal)
    {
        abort_unless($appraisal->status === 'with_employee'
            && auth()->id() === $appraisal->employee?->user_id, 403);

        $data = $request->validate(['employee_comment' => 'required|string|max:2000']);

        $appraisal->update([
            'employee_comment'   => $data['employee_comment'],
            'employee_signed_at' => now(),
            'status'             => 'completed',
        ]);
        $appraisal->log('self_appraised', 'completed', $data['employee_comment']);

        foreach (array_filter([$appraisal->initiated_by, $appraisal->return_to_id, $appraisal->appraiser_id]) as $uid) {
            $this->notify($uid, 'Appraisal completed',
                "{$appraisal->employee?->full_name} has signed off their appraisal — {$appraisal->overall_percent}% ({$appraisal->bandLabel()}).",
                $appraisal);
        }

        return back()->with('success', 'Thank you — your appraisal is complete.');
    }

    // ── Part II actions ──────────────────────────────────────────────────

    public function storeAction(Request $request, Appraisal $appraisal)
    {
        $this->authoriseView($appraisal);
        abort_if($appraisal->status === 'completed', 403, 'This appraisal is closed.');

        $data = $request->validate([
            'problem_area'    => 'required|string|max:1000',
            'remedial_action' => 'nullable|string|max:1000',
            'by_when'         => 'nullable|date',
        ]);

        $appraisal->actions()->create($data + [
            'sort_order' => (int) $appraisal->actions()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Development area added.');
    }

    public function destroyAction(Appraisal $appraisal, AppraisalAction $action)
    {
        abort_if($action->appraisal_id !== $appraisal->id, 404);
        $this->authoriseView($appraisal);
        abort_if($appraisal->status === 'completed', 403);

        $action->delete();
        return back()->with('success', 'Development area removed.');
    }

    // ── Evidence ─────────────────────────────────────────────────────────

    public function storeAttachment(Request $request, Appraisal $appraisal)
    {
        $this->authoriseView($appraisal);

        $data = $request->validate([
            'file'             => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp',
            'label'            => 'nullable|string|max:255',
            'appraisal_kpi_id' => 'nullable|exists:appraisal_kpis,id',
        ]);

        $file = $request->file('file');
        // Stored off the web root; served through download() so access is checked.
        $path = $file->store("appraisals/{$appraisal->id}", 'local');

        $appraisal->attachments()->create([
            'appraisal_kpi_id' => $data['appraisal_kpi_id'] ?? null,
            'uploaded_by'      => auth()->id(),
            'label'            => $data['label'] ?? null,
            'file_path'        => $path,
            'original_name'    => $file->getClientOriginalName(),
            'mime_type'        => $file->getClientMimeType(),
            'size'             => $file->getSize(),
        ]);

        return back()->with('success', 'Evidence attached.');
    }

    public function downloadAttachment(Appraisal $appraisal, AppraisalAttachment $attachment)
    {
        abort_if($attachment->appraisal_id !== $appraisal->id, 404);
        $this->authoriseView($appraisal);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404, 'File is missing.');

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    public function destroyAttachment(Appraisal $appraisal, AppraisalAttachment $attachment)
    {
        abort_if($attachment->appraisal_id !== $appraisal->id, 404);
        $this->authoriseView($appraisal);
        abort_unless($attachment->uploaded_by === auth()->id()
            || auth()->user()->hasAnyRole(['super-admin', 'hr-admin']), 403);

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Evidence removed.');
    }
}
