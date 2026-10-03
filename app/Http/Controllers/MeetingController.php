<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\QualityDocument;
use App\Models\MeetingFile;
use App\Models\{Meeting, MeetingParticipant, Employee};
use App\Mail\MeetingInviteMail;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $meetings = Meeting::with(['organizer','participants'])
            ->when($request->search, fn($q) => $q->where('title','like',"%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('start_at')->paginate(15);
        return view('meetings.index', compact('meetings'));
    }

    public function create()
    {
        $employees = Employee::with('user')->where('status','active')->get();
        return view('meetings.create', compact('employees'));
    }

    /**
     * Attach files to a meeting that already exists.
     *
     * Organizer only. A participant who could add papers could also add them
     * after everybody had read the pack.
     */
    public function uploadFiles(Request $request, Meeting $meeting)
    {
        abort_unless($this->canOrganise($request->user(), $meeting), 403);

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:' . QualityDocument::maxUploadKb(),
        ]);

        $this->storeFiles($request, $meeting);

        return back()->with('success', 'Attached to the meeting.');
    }

    public function destroyFile(Request $request, Meeting $meeting, MeetingFile $file)
    {
        abort_unless($file->meeting_id === $meeting->id, 404);
        abort_unless($this->canOrganise($request->user(), $meeting), 403);

        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', 'Attachment removed.');
    }

    /**
     * Stream a meeting file.
     *
     * The organizer and the invited participants, and nobody else — these are
     * the papers for one meeting, not company reading. Admins are included
     * because they already administer the record.
     */
    public function downloadFile(Request $request, Meeting $meeting, MeetingFile $file)
    {
        abort_unless($file->meeting_id === $meeting->id, 404);

        $user = $request->user();
        abort_unless(
            $meeting->involves($user) || $user->hasAnyRole(['super-admin', 'hr-admin']),
            403,
            'This file belongs to a meeting you are not part of.'
        );

        abort_unless(Storage::disk('local')->exists($file->path), 404, 'File not found.');

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    /** Who may add or remove papers: the organizer, or an administrator. */
    private function canOrganise(?User $user, Meeting $meeting): bool
    {
        if (! $user) {
            return false;
        }

        return $meeting->organizer_id === $user->employee?->id
            || $user->hasAnyRole(['super-admin', 'hr-admin']);
    }

    private function storeFiles(Request $request, Meeting $meeting): void
    {
        foreach ((array) $request->file('files', []) as $upload) {
            if (! $upload) {
                continue;
            }

            MeetingFile::create([
                'meeting_id'    => $meeting->id,
                // Private disk: a meeting pack is not public, and a guessable
                // URL under public/ would be.
                'path'          => $upload->store('meetings/' . $meeting->id, 'local'),
                'original_name' => $upload->getClientOriginalName(),
                'mime'          => $upload->getClientMimeType(),
                'size'          => $upload->getSize(),
                'uploaded_by'   => $request->user()->id,
            ]);
        }
    }
    public function store(Request $request)
    {
        $request->validate([
            'title'                 => 'required',
            'start_at'              => 'required|date',
            'end_at'                => 'required|date|after:start_at',
            'recurrence'            => 'nullable|in:daily,weekly,biweekly,monthly',
            'recurrence_end_date'   => 'nullable|date|after:start_at',
            // Papers for the meeting. No format is refused — an agenda, a board
            // pack, a spreadsheet, a slide deck — only the size is capped.
            'files.*'               => 'nullable|file|max:' . QualityDocument::maxUploadKb(),
        ]);

        $meeting = Meeting::create([
            'title'                => $request->title,
            'organizer_id'         => auth()->user()->employee?->id,
            'start_at'             => $request->start_at,
            'end_at'               => $request->end_at,
            'location'             => $request->location,
            'description'          => $request->description,
            'status'               => 'scheduled',
            'recurrence'           => $request->recurrence ?: null,
            'recurrence_end_date'  => $request->recurrence_end_date ?: null,
        ]);

        $this->storeFiles($request, $meeting);

        if ($request->participants) {
            foreach ($request->participants as $empId) {
                MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $empId, 'rsvp' => 'pending']);
            }
            // Queue invite emails to all participants
            $employees = Employee::with('user')->whereIn('id', $request->participants)->get();
            $ns = app(NotificationService::class);
            foreach ($employees as $employee) {
                if ($employee->user?->email) {
                    Mail::to($employee->user->email)->queue(new MeetingInviteMail($meeting, $employee));
                }
                $ns->meetingInvite($meeting, $employee);
            }
        }

        return redirect()->route('meetings.show', $meeting)->with('success', 'Meeting scheduled. Invites sent to participants.');
    }

    public function show(Meeting $meeting)
    {
        $meeting->load(['organizer','participants.employee.designation']);
        return view('meetings.show', compact('meeting'));
    }

    public function edit(Meeting $meeting)
    {
        $employees = Employee::with('user')->where('status','active')->get();
        return view('meetings.edit', compact('meeting','employees'));
    }

    public function update(Request $request, Meeting $meeting)
    {
        $meeting->update($request->only('title','start_at','end_at','location','description'));
        if ($request->participants !== null) {
            $meeting->participants()->delete();
            foreach ($request->participants as $empId) {
                MeetingParticipant::create(['meeting_id'=>$meeting->id,'employee_id'=>$empId,'rsvp'=>'pending']);
            }
        }
        return redirect()->route('meetings.show', $meeting)->with('success', 'Meeting updated.');
    }

    public function destroy(Meeting $meeting) { $meeting->delete(); return redirect()->route('meetings.index')->with('success', 'Deleted.'); }

    public function rsvp(Request $request, Meeting $meeting)
    {
        $request->validate(['rsvp' => 'required|in:accepted,declined']);
        MeetingParticipant::where('meeting_id', $meeting->id)->where('employee_id', auth()->user()->employee?->id)->update(['rsvp' => $request->rsvp]);
        return back()->with('success', 'RSVP updated.');
    }

    public function cancel(Meeting $meeting)
    {
        $meeting->update(['status' => 'cancelled']);
        return back()->with('success', 'Meeting cancelled.');
    }

    public function calendar()
    {
        $events = Meeting::where('status','!=','cancelled')->get()->map(fn($m) => [
            'id'    => $m->id,
            'title' => $m->title,
            'start' => $m->start_at,
            'end'   => $m->end_at,
            'color' => $m->status === 'completed' ? '#10b981' : '#1e40af',
        ]);
        return view('meetings.calendar', ['calendarEvents' => $events]);
    }
}