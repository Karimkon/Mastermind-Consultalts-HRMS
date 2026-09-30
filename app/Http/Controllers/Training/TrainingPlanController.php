<?php
namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\{Employee, Notification, TrainingCourse, TrainingSession,
                TrainingSessionParticipant, User};
use App\Mail\TrainingNoticeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Mail};

/**
 * The annual training plan and its approval chain.
 *
 * A session moves initiator -> HR -> CEO. Nobody approves their own submission,
 * and the plan is only committed spend once the CEO has signed it.
 */
class TrainingPlanController extends Controller
{
    private function isHr(): bool
    {
        return auth()->user()->hasAnyRole(['hr-admin', 'super-admin']);
    }

    private function isCeo(): bool
    {
        return auth()->user()->hasAnyRole(['md', 'super-admin']);
    }

    /** Managers and supervisors may put training forward; HR may too. */
    private function canInitiate(): bool
    {
        return auth()->user()->hasAnyRole(
            ['manager', 'hr-admin', 'super-admin', 'account-manager', 'md']);
    }

    // ── The plan ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $year = (int) ($request->year ?: now()->year);

        $sessions = TrainingSession::with(['participants.employee', 'initiator', 'course'])
            ->where('plan_year', $year)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->orderByRaw('starts_on IS NULL, starts_on')
            ->get();

        // Grouped by month so the plan reads as a year rather than a list.
        $byMonth = $sessions->groupBy(fn($s) => $s->starts_on?->format('Y-m') ?? 'unscheduled');

        $approved = $sessions->filter->isApproved();

        return view('training.plan.index', [
            'year'       => $year,
            'years'      => $this->planYears(),
            'sessions'   => $sessions,
            'byMonth'    => $byMonth,
            'categories' => TrainingSession::where('plan_year', $year)
                                ->whereNotNull('category')->distinct()->pluck('category'),
            'totals'     => [
                'sessions'     => $sessions->count(),
                'approved'     => $approved->count(),
                'seats'        => $sessions->sum(fn($s) => $s->participants->count()),
                'cost'         => $sessions->sum(fn($s) => $s->totalCost()),
                'approvedCost' => $approved->sum(fn($s) => $s->totalCost()),
                'awaiting'     => $sessions->whereIn('status', ['pending_hr', 'pending_ceo'])->count(),
            ],
            'canInitiate' => $this->canInitiate(),
        ]);
    }

    /** Years worth offering: everything on record, plus this year and next. */
    private function planYears(): array
    {
        $years = TrainingSession::distinct()->pluck('plan_year')->all();
        $years = array_merge($years, [now()->year, now()->year + 1]);
        $years = array_unique(array_map('intval', $years));
        rsort($years);
        return $years;
    }

    public function create()
    {
        abort_unless($this->canInitiate(), 403, 'You cannot put training forward.');

        return view('training.plan.edit', [
            'session'   => new TrainingSession(['plan_year' => now()->year, 'delivery' => 'classroom']),
            'courses'   => TrainingCourse::where('is_active', true)->orderBy('title')->get(),
            'employees' => Employee::with('department')->where('status', 'active')
                                ->orderBy('first_name')->get(),
        ]);
    }

    public function edit(TrainingSession $session)
    {
        abort_unless($this->canInitiate(), 403);
        abort_unless($session->isEditable() || $this->isHr(), 403,
            'This session is in the approval chain and can no longer be edited.');

        return view('training.plan.edit', [
            'session'   => $session->load('participants.employee'),
            'courses'   => TrainingCourse::where('is_active', true)->orderBy('title')->get(),
            'employees' => Employee::with('department')->where('status', 'active')
                                ->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->canInitiate(), 403);

        $session = TrainingSession::create($this->validated($request) + [
            'status'       => 'draft',
            'initiated_by' => auth()->id(),
        ]);

        $this->syncParticipants($request, $session);

        return redirect()->route('training.plan.show', $session)
            ->with('success', 'Training added to the plan as a draft. Submit it when you are ready.');
    }

    public function update(Request $request, TrainingSession $session)
    {
        abort_unless($this->canInitiate(), 403);
        abort_unless($session->isEditable() || $this->isHr(), 403,
            'This session is in the approval chain and can no longer be edited.');

        $session->update($this->validated($request));
        $this->syncParticipants($request, $session);

        return back()->with('success', 'Training updated.');
    }

    public function show(TrainingSession $session)
    {
        $session->load(['participants.employee.department', 'participants.recommender',
                        'initiator', 'hrApprover', 'ceoApprover', 'course']);

        return view('training.plan.show', [
            'session'    => $session,
            'employees'  => Employee::with('department')->where('status', 'active')
                                ->orderBy('first_name')->get(),
            'canApprove' => $this->approvalAvailableTo($session),
            'canEdit'    => $this->canInitiate() && ($session->isEditable() || $this->isHr()),
        ]);
    }

    /**
     * Which decision, if any, this user may take on this session right now.
     *
     * Returns 'hr', 'ceo' or null. Nobody signs off their own submission: an HR
     * administrator who raised a session is its initiator at that moment, not
     * its approver.
     */
    private function approvalAvailableTo(TrainingSession $session): ?string
    {
        if ($session->status === 'pending_hr' && $this->isHr()
            && $session->initiated_by !== auth()->id()) {
            return 'hr';
        }

        if ($session->status === 'pending_ceo' && $this->isCeo()) {
            return 'ceo';
        }

        return null;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'              => 'required|string|max:255',
            'training_course_id' => 'nullable|exists:training_courses,id',
            'category'           => 'nullable|string|max:100',
            'delivery'           => 'required|in:classroom,e_learning,on_the_job,external',
            'plan_year'          => 'required|integer|min:2020|max:2100',
            'starts_on'          => 'nullable|date',
            'ends_on'            => 'nullable|date|after_or_equal:starts_on',
            'duration_days'      => 'nullable|numeric|min:0|max:365',
            'duration_hours'     => 'nullable|numeric|min:0|max:2000',
            'venue'              => 'nullable|string|max:255',
            'trainer'            => 'nullable|string|max:255',
            'provider'           => 'nullable|string|max:255',
            'max_participants'   => 'nullable|integer|min:1|max:1000',
            'cost_pedagogic'     => 'nullable|numeric|min:0',
            'cost_logistic'      => 'nullable|numeric|min:0',
            'cost_remuneration'  => 'nullable|numeric|min:0',
            'cost_company'       => 'nullable|numeric|min:0',
            'justification'      => 'nullable|string|max:2000',
            'notes'              => 'nullable|string|max:2000',
        ], [
            'ends_on.after_or_equal' => 'The end date cannot be before the start date.',
        ]);
    }

    /** Add anybody newly ticked. Nobody is removed here - that is its own act. */
    private function syncParticipants(Request $request, TrainingSession $session): void
    {
        foreach ((array) $request->input('employee_ids', []) as $employeeId) {
            $participant = TrainingSessionParticipant::firstOrCreate(
                ['training_session_id' => $session->id, 'employee_id' => $employeeId],
                ['recommended_by' => auth()->id(), 'attendance' => 'nominated']
            );

            // Only the people newly added. Picking somebody on the create screen
            // told them nothing at all, while nominating the same person from the
            // session page did - the same act, two different outcomes.
            if ($participant->wasRecentlyCreated) {
                $participant->setRelation('employee', $participant->employee);
                $this->tellEmployee($participant, $session);
            }
        }
    }

    // ── Nominations ──────────────────────────────────────────────────────────

    public function addParticipant(Request $request, TrainingSession $session)
    {
        abort_unless($this->canInitiate(), 403);

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'reason'      => 'nullable|string|max:500',
        ]);

        if ($session->max_participants
            && $session->participants()->count() >= $session->max_participants) {
            return back()->with('error',
                "This session is capped at {$session->max_participants} seat(s).");
        }

        $participant = TrainingSessionParticipant::firstOrNew([
            'training_session_id' => $session->id,
            'employee_id'         => $data['employee_id'],
        ]);

        if ($participant->exists) {
            return back()->with('error', 'That employee is already on this session.');
        }

        $participant->fill([
            'recommended_by' => auth()->id(),
            'reason'         => $data['reason'] ?? null,
            'attendance'     => 'nominated',
        ])->save();

        $this->tellEmployee($participant, $session);

        return back()->with('success', 'Nominated.');
    }

    public function removeParticipant(TrainingSession $session, TrainingSessionParticipant $participant)
    {
        abort_if($participant->training_session_id !== $session->id, 404);
        abort_unless($this->canInitiate(), 403);

        $participant->delete();

        return back()->with('success', 'Removed from this session.');
    }

    /** Attendance, result and the return-on-training measure. */
    public function updateParticipant(Request $request, TrainingSession $session,
                                      TrainingSessionParticipant $participant)
    {
        abort_if($participant->training_session_id !== $session->id, 404);
        abort_unless($this->canInitiate(), 403);

        $participant->update($request->validate([
            'attendance'          => 'required|in:nominated,confirmed,attended,absent,withdrawn',
            'completed_on'        => 'nullable|date',
            'score'               => 'nullable|numeric|min:0|max:100',
            'roi_metric'          => 'nullable|string|max:255',
            'roi_unit'            => 'nullable|string|max:40',
            'roi_before'          => 'nullable|numeric',
            'roi_after'           => 'nullable|numeric',
            'roi_lower_is_better' => 'nullable|boolean',
            'roi_measured_on'     => 'nullable|date',
            'roi_note'            => 'nullable|string|max:1000',
        ]));

        return back()->with('success', 'Participant updated.');
    }

    // ── The approval chain ───────────────────────────────────────────────────

    public function submit(TrainingSession $session)
    {
        abort_unless($session->isEditable(), 403, 'This session has already been submitted.');
        abort_unless($session->initiated_by === auth()->id() || $this->isHr(), 403);

        if ($session->participants()->count() === 0) {
            return back()->with('error', 'Nominate at least one person before submitting.');
        }

        $session->update(['status' => 'pending_hr', 'decision_note' => null]);

        $this->notifyRole(['hr-admin'], 'Training awaiting your approval',
            auth()->user()->name . ' submitted "' . $session->title . '" ('
            . number_format($session->totalCost()) . ' for '
            . $session->participants()->count() . ' person(s)) for HR approval.', $session);

        return back()->with('success', 'Submitted to HR.');
    }

    public function approve(Request $request, TrainingSession $session)
    {
        $stage = $this->approvalAvailableTo($session);
        abort_unless($stage, 403, 'This session is not with you for approval.');

        if ($stage === 'hr') {
            $session->update([
                'status'         => 'pending_ceo',
                'hr_approved_by' => auth()->id(),
                'hr_approved_at' => now(),
            ]);

            $this->notifyRole(['md'], 'Training awaiting your approval',
                'HR approved "' . $session->title . '" ('
                . number_format($session->totalCost()) . '). It needs your sign-off.', $session);

            $this->tellInitiator($session, 'HR approved your training request',
                '"' . $session->title . '" has passed HR and is with the CEO.');

            return back()->with('success', 'Approved and sent to the CEO.');
        }

        $session->update([
            'status'          => 'approved',
            'ceo_approved_by' => auth()->id(),
            'ceo_approved_at' => now(),
        ]);

        $this->tellInitiator($session, 'Training approved',
            '"' . $session->title . '" has been approved and is committed to the plan.');

        foreach ($session->participants as $p) {
            $this->tellEmployee($p, $session, true);
        }

        return back()->with('success', 'Approved. The training is committed to the plan.');
    }

    public function reject(Request $request, TrainingSession $session)
    {
        $stage = $this->approvalAvailableTo($session);
        abort_unless($stage, 403, 'This session is not with you for approval.');

        $data = $request->validate(
            ['decision_note' => 'required|string|max:1000'],
            ['decision_note.required' => 'Say why this is being sent back.']
        );

        $session->update(['status' => 'rejected', 'decision_note' => $data['decision_note']]);

        $this->tellInitiator($session, 'Training request sent back',
            '"' . $session->title . '" was not approved: ' . $data['decision_note']);

        return back()->with('success', 'Sent back to the initiator with your reason.');
    }

    public function complete(TrainingSession $session)
    {
        abort_unless($this->isHr(), 403, 'Only HR can close a session.');
        abort_unless($session->isApproved(), 403, 'Only an approved session can be completed.');

        $session->update(['status' => 'completed']);

        return back()->with('success', 'Session marked complete. Record attendance and results below.');
    }

    // ── Telling people ───────────────────────────────────────────────────────

    /**
     * Tell one user, in the app and by email.
     *
     * Every notice in this module went in-app only, so a training request could
     * sit with HR or the CEO until somebody happened to open the system. The
     * rest of the platform sends both; this brings training into line.
     *
     * A failed email never unwinds the action that caused it - the approval
     * stands and the reason is logged.
     */
    private function notifyUser(?int $userId, string $title, string $body,
                                TrainingSession $session, ?string $subject = null): void
    {
        if (! $userId || $userId === auth()->id()) return;

        Notification::create([
            'user_id' => $userId,
            'type'    => 'training',
            'title'   => $title,
            'body'    => $body,
            'data'    => ['session_id' => $session->id,
                          'url' => route('training.plan.show', $session)],
        ]);

        $user = User::find($userId);
        if (! $user?->email) return;

        try {
            Mail::to($user->email)->send(
                new TrainingNoticeMail($session, $title, $body, $subject ?: $title));
        } catch (\Throwable $e) {
            Log::warning('Training email failed', [
                'session_id' => $session->id,
                'user_id'    => $userId,
                'reason'     => $e->getMessage(),
            ]);
        }
    }

    private function notifyRole(array $roles, string $title, string $body, TrainingSession $session): void
    {
        foreach (User::role($roles)->get() as $user) {
            $this->notifyUser($user->id, $title, $body, $session);
        }
    }

    private function tellInitiator(TrainingSession $session, string $title, string $body): void
    {
        $this->notifyUser($session->initiated_by, $title, $body, $session);
    }

    /** Tell the person they are down for training - only once it is real. */
    private function tellEmployee(TrainingSessionParticipant $p, TrainingSession $session,
                                 bool $approved = false): void
    {
        $title = $approved
            ? 'You are booked on training'
            : 'You have been put forward for training';

        $body = '"' . $session->title . '"'
            . ($session->starts_on ? ' on ' . $session->starts_on->format('d M Y') : '')
            . ($session->venue ? ' at ' . $session->venue : '')
            . ($approved
                ? '. This is now approved and confirmed.'
                : '. It still has to go through HR and the CEO, so treat it as provisional.');

        $this->notifyUser($p->employee?->user_id, $title, $body, $session);
    }
}
