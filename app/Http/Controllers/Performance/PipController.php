<?php
namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\{Employee, Pip, PipAttachment, PerformanceCycle, Notification};
use App\Mail\PipCreatedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Mail, Storage};

class PipController extends Controller
{
    public function index()
    {
        $user     = auth()->user();
        $employee = $user->employee;

        // This listed every improvement plan in the company to anybody who
        // opened it, and the route is open to the employee role — so any of
        // the 1,093 staff accounts could read who was on a plan, and for what.
        // show() has always checked ownership and the goals list beside it
        // scopes correctly; this was the one that did not.
        $pips = Pip::with(['employee.user', 'cycle'])
            ->when(! $this->isAdmin(), function ($q) use ($employee, $user) {
                $q->where(function ($mine) use ($employee, $user) {
                    // Yours, or one you opened for somebody who reports to you.
                    $mine->where('created_by', $user->id);
                    if ($employee) $mine->orWhere('employee_id', $employee->id);
                });
            })
            ->orderByDesc('created_at')->paginate(20);

        return view('performance.pip.index', compact('pips'));
    }

    public function create()
    {
        $employees = Employee::with('user')->where('status','active')->get();
        $cycles    = PerformanceCycle::orderByDesc('year')->get();
        return view('performance.pip.create', compact('employees','cycles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'title'       => 'required|string',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
        ]);

        $objectives = [];
        if ($request->objectives) {
            foreach ($request->objectives as $obj) {
                if (!empty($obj)) $objectives[] = $obj;
            }
        }

        $pip = Pip::create([
            'employee_id' => $request->employee_id,
            'cycle_id'    => $request->cycle_id,
            'title'       => $request->title,
            'description' => $request->description,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'objectives'  => $objectives,
            'status'      => 'active',
            'created_by'  => auth()->id(),
        ]);

        return redirect()->route('pips.index')
            ->with('success', 'PIP created. ' . $this->tellEmployee($pip));
    }

    /**
     * Tell the employee, in the app and by email.
     *
     * The in-app row carried no `url`, so it was not clickable - a plan the
     * employee was told to review, with nothing to click. And no email was sent
     * at all, while the banner claimed they had been notified. A PIP has dates
     * and consequences, so it cannot wait for somebody to notice a bell.
     *
     * Returns a sentence for the banner: "saved" and "they have been told" are
     * different facts, and whoever opened the plan needs to know which happened.
     */
    private function tellEmployee(Pip $pip, array $changes = []): string
    {
        $user = $pip->employee?->user;

        if (! $user) {
            return ($pip->employee?->full_name ?? 'The employee')
                . ' has no system account, so no message was sent.';
        }

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'pip',
            'title'   => $changes
                ? 'Your improvement plan has been updated'
                : 'Performance Improvement Plan Created',
            'body'    => $changes
                ? "'{$pip->title}' has changed: " . implode('; ', array_map(
                      fn($label, $change) => "{$label} {$change}",
                      array_keys($changes), $changes))
                : "A PIP titled '{$pip->title}' has been created for you. Please review it.",
            'data'    => ['pip_id' => $pip->id, 'url' => route('pips.show', $pip)],
        ]);

        if (! $user->email) {
            return 'Posted in the app; there is no email address on file.';
        }

        try {
            Mail::to($user->email)->send(new PipCreatedMail($pip, $changes));
            return ($pip->employee?->first_name ?? 'They') . ' has been notified in the app and by email.';
        } catch (\Throwable $e) {
            // A failed email must not lose the plan that caused it.
            Log::warning('PIP email failed', ['pip_id' => $pip->id, 'reason' => $e->getMessage()]);
            return 'Posted in the app, but the email could not be sent.';
        }
    }

    public function show(Pip $pip)
    {
        $this->authorisePip($pip);

        return view('performance.pip.show', [
            'pip'     => $pip->load(['employee.user', 'cycle', 'attachments.uploader']),
            'isOwner' => $this->isOwner($pip),
        ]);
    }

    /** True when the signed-in user is the person the plan is about. */
    private function isOwner(Pip $pip): bool
    {
        return auth()->id() === $pip->employee?->user_id;
    }

    private function isAdmin(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager', 'md']);
    }

    /**
     * Who may see a plan.
     *
     * There was no check here at all: any signed-in employee could open any
     * other employee's improvement plan by walking the URL. A PIP is among the
     * most sensitive records in the system.
     */
    private function authorisePip(Pip $pip): void
    {
        abort_unless(
            $this->isOwner($pip) || $this->isAdmin() || $pip->created_by === auth()->id(),
            403, 'This improvement plan is not yours to view.'
        );
    }

    /**
     * Attach a file - to one objective, or to the plan as a whole.
     *
     * Both sides use this. HR sends the manual somebody is being asked to work
     * to; the employee sends back the updated version against the objective it
     * answers. Files are stored off the web root and served through download()
     * so access is checked every time rather than guessed from a URL.
     */
    public function storeAttachment(Request $request, Pip $pip)
    {
        $this->authorisePip($pip);

        $data = $request->validate([
            'file'            => \App\Support\Uploads::rules(),
            'note'            => 'nullable|string|max:255',
            'objective_index' => 'nullable|integer|min:0',
        ], \App\Support\Uploads::messages());

        // An index pointing past the end of the list would render nowhere, so it
        // is treated as a general attachment rather than silently lost.
        $index = $data['objective_index'] ?? null;
        $count = is_array($pip->objectives) ? count($pip->objectives) : 0;
        if ($index !== null && $index >= $count) $index = null;

        $file = $request->file('file');
        $path = $file->store("pips/{$pip->id}", 'local');

        $pip->attachments()->create([
            'objective_index' => $index,
            'uploaded_by'     => auth()->id(),
            'note'            => $data['note'] ?? null,
            'file_path'       => $path,
            'original_name'   => $file->getClientOriginalName(),
            'mime_type'       => $file->getClientMimeType(),
            'size'            => $file->getSize(),
        ]);

        return back()->with('success', 'Attached. ' . $this->tellAboutFile($pip, $index, $file->getClientOriginalName()));
    }

    public function downloadAttachment(Pip $pip, PipAttachment $attachment)
    {
        abort_if($attachment->pip_id !== $pip->id, 404);
        $this->authorisePip($pip);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404, 'File is missing.');

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    /** Removable by whoever attached it, or by an administrator. */
    public function destroyAttachment(Pip $pip, PipAttachment $attachment)
    {
        abort_if($attachment->pip_id !== $pip->id, 404);
        $this->authorisePip($pip);
        abort_unless($attachment->uploaded_by === auth()->id() || $this->isAdmin(), 403,
            'Only the person who attached this file, or an administrator, can remove it.');

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    /**
     * Tell the other side a file has landed.
     *
     * The point of attaching to a plan is that somebody else opens it: HR sends
     * a document to be worked on, the employee sends one back. Whichever of them
     * did not upload it is the one who needs to know.
     */
    private function tellAboutFile(Pip $pip, ?int $index, string $filename): string
    {
        $pip->loadMissing('employee.user');

        $objective = $index !== null && is_array($pip->objectives)
            ? ($pip->objectives[$index] ?? null)
            : null;

        $recipientId = $this->isOwner($pip)
            ? $pip->created_by                 // the employee replied; tell whoever set the plan
            : $pip->employee?->user_id;        // HR sent something; tell the employee

        if (! $recipientId || $recipientId === auth()->id()) {
            return 'No one else needed notifying.';
        }

        Notification::create([
            'user_id' => $recipientId,
            'type'    => 'pip',
            'title'   => 'New file on an improvement plan',
            'body'    => auth()->user()->name . ' attached "' . $filename . '" to ' . $pip->title
                . ($objective ? ' against "' . $objective . '"' : '') . '.',
            'data'    => ['pip_id' => $pip->id, 'url' => route('pips.show', $pip)],
        ]);

        return 'The other party has been notified.';
    }

    /** Fields worth telling somebody about, and what to call them. */
    private const WATCHED = [
        'end_date'    => 'End date',
        'status'      => 'Status',
        'outcome'     => 'Outcome',
        'description' => 'Context',
    ];

    public function update(Request $request, Pip $pip)
    {
        $before = $pip->only(array_keys(self::WATCHED));

        $request->validate([
            'file' => \App\Support\Uploads::rules(required: false),
        ], \App\Support\Uploads::messages());

        $pip->update($request->only(['status','outcome','end_date','description']));

        // The outcome and its paperwork arrive in the same submit, so the file
        // is filed here rather than requiring a second trip through the
        // attachment form.
        $filed = '';
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $pip->attachments()->create([
                'objective_index' => null,
                'uploaded_by'     => auth()->id(),
                'note'            => 'Sent with the update',
                'file_path'       => $file->store("pips/{$pip->id}", 'local'),
                'original_name'   => $file->getClientOriginalName(),
                'mime_type'       => $file->getClientMimeType(),
                'size'            => $file->getSize(),
            ]);
            $filed = ' "' . $file->getClientOriginalName() . '" attached.';
        }

        $changes = $this->changesMade($before, $pip->fresh()->only(array_keys(self::WATCHED)));

        // Silent unless something actually moved. Saving a form without editing
        // it should not put mail in somebody's inbox.
        $note = $changes
            ? ' ' . $this->tellEmployee($pip->fresh(), $changes)
            : ' Nothing changed, so nobody was notified.';

        return back()->with('success', 'PIP updated.' . $filed . $note);
    }

    /**
     * Human-readable before/after for the fields that changed.
     *
     * A moved deadline matters as much as the original one, so the employee is
     * told what moved rather than just that something did.
     */
    private function changesMade(array $before, array $after): array
    {
        $changes = [];

        foreach (self::WATCHED as $field => $label) {
            $was = $before[$field] ?? null;
            $now = $after[$field] ?? null;

            $wasText = $this->readable($was);
            $nowText = $this->readable($now);
            if ($wasText === $nowText) continue;

            $changes[$label] = $wasText === '—'
                ? 'set to ' . $nowText
                : 'was ' . $wasText . ', now ' . $nowText;
        }

        return $changes;
    }

    private function readable($value): string
    {
        if ($value instanceof \DateTimeInterface) return $value->format('d M Y');
        if (blank($value)) return '—';
        return (string) $value;
    }

    public function destroy(Pip $pip)
    {
        $pip->delete();
        return back()->with('success', 'PIP deleted.');
    }
}
