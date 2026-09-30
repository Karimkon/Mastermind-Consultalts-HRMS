<?php
namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\{Employee, EmployeeGoal, GoalAttachment, Notification, PerformanceCycle};
use App\Mail\GoalAssignedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Mail, Storage};

class GoalController extends Controller
{
    public function index(Request $request)
    {
        $user     = auth()->user();
        $employee = $user->employee;
        $isAdmin  = $user->hasAnyRole(['super-admin','hr-admin','manager']);

        $goals = EmployeeGoal::with(['employee.user','cycle','attachments.uploader'])
            ->when(!$isAdmin && $employee, fn($q) => $q->where('employee_id', $employee->id))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->cycle_id, fn($q) => $q->where('cycle_id', $request->cycle_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')->paginate(25);

        $cycles    = PerformanceCycle::orderByDesc('year')->get();
        $employees = $isAdmin ? Employee::with('user')->where('status','active')->get() : collect();
        return view('performance.goals.index', compact('goals','cycles','employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'employee_id' => 'required|exists:employees,id',
            'weight'      => 'nullable|numeric|min:0|max:100',
            'target_date' => 'nullable|date',
        ]);

        $goal = EmployeeGoal::create([
            'employee_id' => $request->employee_id,
            'cycle_id'    => $request->cycle_id,
            'title'       => $request->title,
            'description' => $request->description,
            'target_date' => $request->target_date,
            'weight'      => $request->weight ?? 0,
            'status'      => 'not_started',
            'progress'    => 0,
            'created_by'  => auth()->id(),
        ]);

        return back()->with('success', 'Goal created. ' . $this->tellEmployee($goal));
    }

    /**
     * Tell the employee a goal has been set for them.
     *
     * Goals were being assigned in silence - no email, no in-app row, nothing.
     * Somebody could be measured at the end of a cycle against a goal they were
     * never told about, which is the one outcome a goal is supposed to prevent.
     *
     * Returns a sentence for the banner, so whoever set the goal can see whether
     * the person was actually reached.
     */
    private function tellEmployee(EmployeeGoal $goal, array $changes = []): string
    {
        $goal->loadMissing(['employee.user', 'cycle']);
        $user = $goal->employee?->user;

        if (! $user) {
            return ($goal->employee?->full_name ?? 'The employee')
                . ' has no system account, so no message was sent.';
        }

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'goal',
            'title'   => $changes ? 'Your goal has been updated' : 'A new goal has been set for you',
            'body'    => $changes
                ? "\"{$goal->title}\" has changed: " . implode('; ', array_map(
                      fn($label, $change) => "{$label} {$change}",
                      array_keys($changes), $changes))
                : "\"{$goal->title}\""
                    . ($goal->target_date ? ' — due ' . $goal->target_date->format('d M Y') : '')
                    . '. Open your goals to see the detail.',
            // There is no per-goal page, so this lands on the list where the new
            // goal appears rather than on a URL that does not exist.
            'data'    => ['goal_id' => $goal->id, 'url' => route('goals.index')],
        ]);

        if (! $user->email) {
            return 'Posted in the app; there is no email address on file.';
        }

        try {
            Mail::to($user->email)->send(new GoalAssignedMail($goal, $changes));
            return ($goal->employee?->first_name ?? 'They') . ' has been notified in the app and by email.';
        } catch (\Throwable $e) {
            Log::warning('Goal email failed', ['goal_id' => $goal->id, 'reason' => $e->getMessage()]);
            return 'Posted in the app, but the email could not be sent.';
        }
    }

    private function isAdmin(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager', 'md']);
    }

    private function isOwner(EmployeeGoal $goal): bool
    {
        return auth()->id() === $goal->employee?->user_id;
    }

    /**
     * Who may touch a goal.
     *
     * The list already scoped itself to the signed-in employee, but update,
     * progress and delete took a goal straight off the URL and checked nothing -
     * so any signed-in user could move or destroy anybody's goal by its id.
     */
    private function authoriseGoal(EmployeeGoal $goal): void
    {
        abort_unless(
            $this->isOwner($goal) || $this->isAdmin() || $goal->created_by === auth()->id(),
            403, 'This goal is not yours.'
        );
    }

    /**
     * Attach evidence to a goal.
     *
     * A goal is scored on whether it was met, and "met" is an assertion until
     * something backs it. Either side may attach: the employee the deliverable,
     * the manager the brief it was set from. Files sit off the web root and are
     * served through download() so access is checked each time.
     */
    public function storeAttachment(Request $request, EmployeeGoal $goal)
    {
        $this->authoriseGoal($goal);

        $data = $request->validate([
            'file' => \App\Support\Uploads::rules(),
            'note' => 'nullable|string|max:255',
        ], \App\Support\Uploads::messages());

        $file = $request->file('file');

        $goal->attachments()->create([
            'uploaded_by'   => auth()->id(),
            'note'          => $data['note'] ?? null,
            'file_path'     => $file->store("goals/{$goal->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return back()->with('success', 'Attached. ' . $this->tellAboutFile($goal, $file->getClientOriginalName()));
    }

    public function downloadAttachment(EmployeeGoal $goal, GoalAttachment $attachment)
    {
        abort_if($attachment->employee_goal_id !== $goal->id, 404);
        $this->authoriseGoal($goal);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404, 'File is missing.');

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    /** Removable by whoever attached it, or by HR. */
    public function destroyAttachment(EmployeeGoal $goal, GoalAttachment $attachment)
    {
        abort_if($attachment->employee_goal_id !== $goal->id, 404);
        $this->authoriseGoal($goal);
        abort_unless($attachment->uploaded_by === auth()->id() || $this->isAdmin(), 403,
            'Only the person who attached this file, or HR, can remove it.');

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    /** Tell whichever side did not upload it. */
    private function tellAboutFile(EmployeeGoal $goal, string $filename): string
    {
        $goal->loadMissing('employee.user');

        $recipientId = $this->isOwner($goal) ? $goal->created_by : $goal->employee?->user_id;

        if (! $recipientId || $recipientId === auth()->id()) {
            return 'No one else needed notifying.';
        }

        Notification::create([
            'user_id' => $recipientId,
            'type'    => 'goal',
            'title'   => 'New file on a goal',
            'body'    => auth()->user()->name . ' attached "' . $filename . '" to ' . $goal->title . '.',
            'data'    => ['goal_id' => $goal->id, 'url' => route('goals.index')],
        ]);

        return 'The other party has been notified.';
    }

    /** Fields worth telling somebody about, and what to call them. */
    private const WATCHED = [
        'title'       => 'Goal',
        'target_date' => 'Target date',
        'weight'      => 'Weight',
        'status'      => 'Status',
        'description' => 'Detail',
    ];

    public function update(Request $request, EmployeeGoal $goal)
    {
        $this->authoriseGoal($goal);

        $before = $goal->only(array_keys(self::WATCHED));

        $goal->update($request->only(['title','description','target_date','weight','status','cycle_id']));

        $changes = $this->changesMade($before, $goal->fresh()->only(array_keys(self::WATCHED)));

        // Silent unless something actually moved - saving an untouched form
        // should not put mail in somebody's inbox.
        $note = $changes
            ? ' ' . $this->tellEmployee($goal->fresh(), $changes)
            : ' Nothing changed, so nobody was notified.';

        return back()->with('success', 'Goal updated.' . $note);
    }

    /**
     * Human-readable before/after for the fields that changed.
     *
     * A moved target date or a re-weighted goal changes what somebody is
     * measured on, so they are told what moved rather than just that it did.
     */
    private function changesMade(array $before, array $after): array
    {
        $changes = [];

        foreach (self::WATCHED as $field => $label) {
            $wasText = $this->readable($before[$field] ?? null, $field);
            $nowText = $this->readable($after[$field] ?? null, $field);
            if ($wasText === $nowText) continue;

            $changes[$label] = $wasText === '—'
                ? 'set to ' . $nowText
                : 'was ' . $wasText . ', now ' . $nowText;
        }

        return $changes;
    }

    private function readable($value, string $field = ''): string
    {
        if ($value instanceof \DateTimeInterface) return $value->format('d M Y');
        if ($field === 'weight') {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . '%';
        }
        if (blank($value)) return '—';
        return (string) $value;
    }

    public function updateProgress(Request $request, EmployeeGoal $goal)
    {
        $this->authoriseGoal($goal);

        $request->validate(['progress' => 'required|integer|min:0|max:100']);
        $progress = (int) $request->progress;
        $status   = $goal->status;

        if ($progress === 100) $status = 'achieved';
        elseif ($progress > 0) $status = 'in_progress';

        $goal->update(['progress' => $progress, 'status' => $status]);
        return back()->with('success', 'Progress updated.');
    }

    public function destroy(EmployeeGoal $goal)
    {
        // Deleting somebody's goal is not theirs to do - it removes the thing
        // they are measured on. Owners may report progress; only HR or a manager
        // may remove the goal itself.
        abort_unless($this->isAdmin(), 403, 'Only HR or a manager can delete a goal.');

        $goal->delete();
        return back()->with('success', 'Goal deleted.');
    }
}
