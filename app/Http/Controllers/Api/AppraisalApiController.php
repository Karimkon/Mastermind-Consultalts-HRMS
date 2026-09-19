<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * Appraisals on the phone.
 *
 * The app talked to `bsc/*` — the cycle-based scheme the per-employee appraisal
 * tables replaced. Both sets of tables still exist and both are empty, so nobody
 * had noticed that the screen on the handset and the screen in the browser were
 * reading different systems.
 *
 * A card moves through four hands and the order is the whole design:
 *
 *     draft            HR sets the KPIs and their weights
 *     with_appraiser   the appraiser scores what was actually achieved
 *     with_manager     the line manager confirms, or sends it back
 *     with_employee    the employee reads it, comments, and signs
 *     completed
 *
 * Every guard below is copied from the web controller rather than reinterpreted,
 * because an appraisal that can be scored by the wrong person, or signed before it
 * was confirmed, is worse than one that cannot be reached from a phone at all.
 *
 * Setting KPIs is deliberately absent. That is deskwork — weights have to total
 * 100 across a table of targets — and it belongs in the browser.
 */
class AppraisalApiController extends Controller
{
    /**
     * Cards this person has something to do with.
     *
     * Three groups, because those are the three reasons a card matters to you:
     * it is yours, you are scoring it, or you are waiting to confirm it.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $employeeId = $request->user()->employee?->id;

        $appraisals = Appraisal::query()
            ->with(['employee.user', 'appraiser', 'template'])
            ->where(function ($q) use ($userId, $employeeId) {
                $q->where('appraiser_id', $userId)
                    ->orWhere('initiated_by', $userId)
                    ->orWhere('return_to_id', $userId);

                if ($employeeId) {
                    $q->orWhere('employee_id', $employeeId);
                }
            })
            ->latest()
            ->get();

        return response()->json([
            'data' => $appraisals->map(fn (Appraisal $a) => $this->summarise($a, $request)),
        ]);
    }

    /** The employee's own card, which is what the self-service tab shows. */
    public function mine(Request $request)
    {
        $employeeId = $request->user()->employee?->id;

        if (! $employeeId) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => Appraisal::with(['appraiser', 'template'])
                ->where('employee_id', $employeeId)
                ->latest()
                ->get()
                ->map(fn (Appraisal $a) => $this->summarise($a, $request)),
        ]);
    }

    public function show(Request $request, Appraisal $appraisal)
    {
        $this->authoriseView($request, $appraisal);

        $appraisal->load(['employee.user', 'appraiser', 'template', 'kpis', 'actions', 'history']);

        return response()->json([
            'data' => array_merge($this->summarise($appraisal, $request), [
                // Field names are the table's, not a tidier set invented here: the
                // app and the browser should be arguing about the same words.
                'kpis' => $appraisal->kpis->map(fn ($k) => [
                    'id' => $k->id,
                    'perspective' => $k->perspective,
                    'kra_name' => $k->kra_name,
                    'performance_measure' => $k->performance_measure,
                    'target' => $k->target,
                    'weightage' => (float) $k->weightage,
                    'actual_achieved' => $k->actual_achieved,
                    'rating' => $k->rating,
                    // Carried separately from `rating`, which is the appraiser's.
                    // Where the two disagree the app shows both, because that gap
                    // is the conversation rather than an error to reconcile.
                    'self_rating' => $k->self_rating,
                    'self_note' => $k->self_note,
                    'evidence_note' => $k->evidence_note,
                    'weighted_index' => $k->weighted_index !== null ? (float) $k->weighted_index : null,
                ])->values(),
                'actions' => $appraisal->actions->map(fn ($a) => [
                    'id' => $a->id,
                    'action' => $a->action,
                    'due_date' => $a->due_date,
                ])->values(),
                'history' => $appraisal->history->map(fn ($h) => [
                    'event' => $h->event,
                    'to_status' => $h->to_status,
                    'comment' => $h->comment,
                    'at' => $h->created_at?->toDateTimeString(),
                ])->values(),
                'employee_comment' => $appraisal->employee_comment,
                'manager_comment' => $appraisal->manager_comment,
            ]),
        ]);
    }

    /**
     * What the employee says they achieved, before anybody rates them.
     *
     * Partial saves, for the same reason the appraiser gets them: a card of
     * nineteen KRAs is not completed in one sitting on a handset, and losing half
     * of it to a validation error would be worse than an incomplete card.
     */
    public function saveSelfAssessment(Request $request, Appraisal $appraisal)
    {
        abort_unless(
            $appraisal->status === 'self_assessment'
                && $request->user()->id === $appraisal->employee?->user_id,
            403,
            'This appraisal is not with you.'
        );

        $rows = $request->validate([
            'kpi' => 'required|array',
            'kpi.*.actual_achieved' => 'nullable|string|max:50',
            'kpi.*.self_rating' => 'nullable|integer|min:1|max:5',
            'kpi.*.self_note' => 'nullable|string|max:1000',
        ])['kpi'];

        foreach ($appraisal->kpis as $kpi) {
            if (! isset($rows[$kpi->id])) {
                continue;
            }

            $kpi->fill([
                'actual_achieved' => $rows[$kpi->id]['actual_achieved'] ?? null,
                'self_rating' => $rows[$kpi->id]['self_rating'] ?? null,
                'self_note' => $rows[$kpi->id]['self_note'] ?? null,
            ])->save();
        }

        return response()->json([
            'data' => $this->summarise($appraisal->refresh(), $request),
            'unrated' => $appraisal->kpis()->whereNull('self_rating')->count(),
        ]);
    }

    /**
     * Hand the card to the appraiser.
     *
     * Every KPI must carry a self-rating. A half-answered card invites the
     * appraiser to fill the gaps themselves, which is what asking the employee
     * first was meant to prevent.
     */
    public function submitSelfAssessment(Request $request, Appraisal $appraisal)
    {
        abort_unless(
            $appraisal->status === 'self_assessment'
                && $request->user()->id === $appraisal->employee?->user_id,
            403,
            'This appraisal is not with you.'
        );

        $unrated = $appraisal->kpis()->whereNull('self_rating')->count();

        if ($unrated > 0) {
            return response()->json([
                'message' => "{$unrated} KPI(s) still have no self-rating. Complete them all before submitting.",
            ], 422);
        }

        $appraisal->update([
            'status' => 'with_appraiser',
            'self_assessed_at' => now(),
        ]);
        $appraisal->log('self_assessment_submitted', 'with_appraiser', null);

        $this->notify(
            $appraisal->appraiser_id,
            'Appraisal ready for scoring',
            "{$appraisal->employee?->full_name} has completed their self-assessment for {$appraisal->title}.",
            $appraisal
        );

        return response()->json(['data' => $this->summarise($appraisal->refresh(), $request)]);
    }

    /**
     * The appraiser records what was actually achieved.
     *
     * Partial saves are allowed on purpose: scoring a dozen KPIs on a phone is not
     * done in one sitting, and losing half of it to a validation error would be
     * worse than an incomplete card. The completeness check belongs at the point
     * the card is handed on, not at every save.
     */
    public function score(Request $request, Appraisal $appraisal)
    {
        abort_unless(
            $appraisal->status === 'with_appraiser' && $request->user()->id === $appraisal->appraiser_id,
            403,
            'This appraisal is not with you for scoring.'
        );

        $rows = $request->validate([
            'kpi' => 'required|array',
            'kpi.*.actual_achieved' => 'nullable|string|max:50',
            'kpi.*.rating' => 'nullable|integer|min:1|max:5',
            'kpi.*.evidence_note' => 'nullable|string|max:255',
        ])['kpi'];

        foreach ($appraisal->kpis as $kpi) {
            if (! isset($rows[$kpi->id])) {
                continue;
            }

            $kpi->fill([
                'actual_achieved' => $rows[$kpi->id]['actual_achieved'] ?? null,
                'rating' => $rows[$kpi->id]['rating'] ?? null,
                'evidence_note' => $rows[$kpi->id]['evidence_note'] ?? null,
            ]);
            $kpi->recalculate();
        }

        $appraisal->recalculate();

        return response()->json([
            'data' => $this->summarise($appraisal->refresh(), $request),
            'unrated' => $appraisal->kpis()->whereNull('rating')->count(),
        ]);
    }

    /**
     * The appraiser hands the card on.
     *
     * An unrated KPI blocks this, exactly as on the web. A card that reaches the
     * manager with gaps in it produces an overall score computed from a subset of
     * the weights, which reads as a real number and is not one.
     */
    public function returnToManager(Request $request, Appraisal $appraisal)
    {
        abort_unless(
            $appraisal->status === 'with_appraiser' && $request->user()->id === $appraisal->appraiser_id,
            403
        );

        $data = $request->validate([
            'return_to_id' => 'required|exists:users,id',
            'comment' => 'nullable|string|max:500',
        ]);

        $unrated = $appraisal->kpis()->whereNull('rating')->count();

        if ($unrated > 0) {
            return response()->json([
                'message' => "{$unrated} KPI(s) still have no rating. Score them all before returning this.",
            ], 422);
        }

        $appraisal->recalculate();
        $appraisal->update(['return_to_id' => $data['return_to_id'], 'status' => 'with_manager']);
        $appraisal->log('returned', 'with_manager', $data['comment'] ?? null);

        $this->notify(
            $data['return_to_id'],
            'Appraisal ready for your confirmation',
            "{$appraisal->appraiser?->name} has completed the appraisal for {$appraisal->employee?->full_name}.",
            $appraisal
        );

        return response()->json(['data' => $this->summarise($appraisal->refresh(), $request)]);
    }

    /** The line manager confirms, which passes the card to the employee. */
    public function confirm(Request $request, Appraisal $appraisal)
    {
        abort_unless(
            $appraisal->status === 'with_manager'
                && $request->user()->id === ($appraisal->return_to_id ?? $appraisal->initiated_by),
            403
        );

        $data = $request->validate(['manager_comment' => 'nullable|string|max:2000']);

        $appraisal->update([
            'manager_comment' => $data['manager_comment'] ?? null,
            'manager_signed_at' => now(),
            'status' => 'with_employee',
        ]);
        $appraisal->log('confirmed', 'with_employee', $data['manager_comment'] ?? null);

        $this->notify(
            $appraisal->employee?->user_id,
            'Your appraisal is ready',
            "Your appraisal for {$appraisal->title} has been confirmed. Please add your own comments and sign it off.",
            $appraisal
        );

        return response()->json(['data' => $this->summarise($appraisal->refresh(), $request)]);
    }

    /** The manager sends it back to the appraiser, with a reason. */
    public function sendBack(Request $request, Appraisal $appraisal)
    {
        abort_unless(
            $appraisal->status === 'with_manager'
                && $request->user()->id === ($appraisal->return_to_id ?? $appraisal->initiated_by),
            403
        );

        // Required, not optional. "Sent back" with no reason tells the appraiser
        // nothing and is the most common way a card ends up bouncing twice.
        $data = $request->validate(['comment' => 'required|string|max:500']);

        $appraisal->update(['status' => 'with_appraiser']);
        $appraisal->log('sent_back', 'with_appraiser', $data['comment']);

        $this->notify(
            $appraisal->appraiser_id,
            'Appraisal sent back to you',
            "The appraisal for {$appraisal->employee?->full_name} needs another look: {$data['comment']}",
            $appraisal
        );

        return response()->json(['data' => $this->summarise($appraisal->refresh(), $request)]);
    }

    /** The employee's own words, which close the card. */
    public function selfAppraise(Request $request, Appraisal $appraisal)
    {
        abort_unless(
            $appraisal->status === 'with_employee'
                && $request->user()->id === $appraisal->employee?->user_id,
            403
        );

        $data = $request->validate(['employee_comment' => 'required|string|max:2000']);

        $appraisal->update([
            'employee_comment' => $data['employee_comment'],
            'employee_signed_at' => now(),
            'status' => 'completed',
        ]);
        $appraisal->log('self_appraised', 'completed', $data['employee_comment']);

        foreach (array_filter([$appraisal->initiated_by, $appraisal->return_to_id, $appraisal->appraiser_id]) as $uid) {
            $this->notify(
                $uid,
                'Appraisal completed',
                "{$appraisal->employee?->full_name} has signed off their appraisal — {$appraisal->overall_percent}% ({$appraisal->bandLabel()}).",
                $appraisal
            );
        }

        return response()->json(['data' => $this->summarise($appraisal->refresh(), $request)]);
    }

    // ── internals ────────────────────────────────────────────────────────

    /**
     * One shape for a card, carrying what the phone needs to decide what to show.
     *
     * `your_move` is the important field. Deriving "can I act on this, and how"
     * on the server keeps one copy of the rules; working it out in the app would
     * put a second, quieter copy of the workflow in Dart.
     */
    private function summarise(Appraisal $a, Request $request): array
    {
        $userId = $request->user()->id;

        $yourMove = match ($a->status) {
            // The employee's first touch. Without this case a card sitting in
            // self_assessment shows no action at all on the phone, which is the
            // one place the person being appraised is most likely to be.
            'self_assessment' => $userId === $a->employee?->user_id ? 'self_assess' : null,
            'with_appraiser' => $userId === $a->appraiser_id ? 'score' : null,
            'with_manager' => $userId === ($a->return_to_id ?? $a->initiated_by) ? 'confirm' : null,
            'with_employee' => $userId === $a->employee?->user_id ? 'self_appraise' : null,
            default => null,
        };

        return [
            'id' => $a->id,
            'title' => $a->title,
            'employee_name' => $a->employee?->full_name,
            'appraiser_name' => $a->appraiser?->name,
            'period' => $a->period,
            'year' => $a->year,
            'review_from' => $a->review_from?->toDateString(),
            'review_to' => $a->review_to?->toDateString(),
            'status' => $a->status,
            'status_label' => $a->statusLabel(),
            'overall_percent' => $a->overall_percent !== null ? (float) $a->overall_percent : null,
            'overall_band' => $a->overall_band,
            'band_label' => $a->overall_band ? $a->bandLabel() : null,
            'kpi_count' => $a->kpis()->count(),
            'unrated_count' => $a->kpis()->whereNull('rating')->count(),
            'your_move' => $yourMove,
        ];
    }

    private function authoriseView(Request $request, Appraisal $appraisal): void
    {
        $userId = $request->user()->id;

        $mayView = $userId === $appraisal->appraiser_id
            || $userId === $appraisal->initiated_by
            || $userId === $appraisal->return_to_id
            || $userId === $appraisal->employee?->user_id
            || $request->user()->hasAnyRole(['super-admin', 'hr-admin', 'md']);

        abort_unless($mayView, 403, 'This appraisal is not yours to read.');
    }

    private function notify(?int $userId, string $title, string $body, Appraisal $a): void
    {
        if (! $userId) {
            return;
        }

        Notification::create([
            'user_id' => $userId,
            'type' => 'appraisal',
            'title' => $title,
            'body' => $body,
            'data' => ['appraisal_id' => $a->id],
        ]);
    }
}
