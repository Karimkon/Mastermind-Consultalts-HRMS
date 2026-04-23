<?php
namespace App\Http\Controllers;

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

    public function store(Request $request)
    {
        $request->validate([
            'title'                 => 'required',
            'start_at'              => 'required|date',
            'end_at'                => 'required|date|after:start_at',
            'recurrence'            => 'nullable|in:daily,weekly,biweekly,monthly',
            'recurrence_end_date'   => 'nullable|date|after:start_at',
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