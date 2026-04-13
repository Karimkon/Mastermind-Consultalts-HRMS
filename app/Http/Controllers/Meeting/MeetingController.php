<?php
namespace App\Http\Controllers\Meeting;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function index()
    {
        $meetings = Meeting::with(["organizer"])->orderByDesc("start_at")->paginate(20);
        return view("meetings.index", compact("meetings"));
    }
    public function create() {
        $employees = Employee::where("status","active")->orderBy("first_name")->get();
        return view("meetings.create", compact("employees"));
    }
    public function store(Request $request) {
        $data = $request->validate(["title" => "required|string|max:200", "description" => "nullable|string", "start_at" => "required|date", "end_at" => "required|date|after:start_at", "location" => "nullable|string|max:200", "meeting_url" => "nullable|url", "type" => "required|in:team,one_on_one,board,client,training,other", "agenda" => "nullable|string"]);
        $organizer = Employee::where("user_id",auth()->id())->first();
        $meeting   = Meeting::create(array_merge($data, ["organizer_id" => $organizer?->id, "status" => "scheduled"]));
        if ($request->filled("participants")) {
            $pids = is_array($request->participants) ? $request->participants : explode(",", $request->participants);
            foreach ($pids as $pid) {
                $meeting->participants()->attach($pid, ["rsvp" => "pending"]);
                $emp = Employee::find($pid);
                if ($emp?->user_id) Notification::create(["user_id" => $emp->user_id, "type" => "meeting_invite", "title" => "Meeting Invitation", "body" => "You have been invited to: {$meeting->title}", "action_url" => "/meetings/{$meeting->id}"]);
            }
        }
        return redirect()->route("meetings.show", $meeting)->with("success","Meeting created.");
    }
    public function show(Meeting $meeting) {
        $meeting->load(["organizer","participants"]);
        return view("meetings.show", compact("meeting"));
    }
    public function edit(Meeting $meeting) {
        $employees = Employee::where("status","active")->orderBy("first_name")->get();
        return view("meetings.edit", compact("meeting","employees"));
    }
    public function update(Request $request, Meeting $meeting) {
        $meeting->update($request->validate(["title" => "required|string|max:200", "start_at" => "required|date", "end_at" => "required|date|after:start_at", "location" => "nullable|string", "status" => "required"]));
        return back()->with("success","Updated.");
    }
    public function destroy(Meeting $meeting) { $meeting->delete(); return redirect()->route("meetings.index")->with("success","Deleted."); }

    public function rsvp(Request $request, Meeting $meeting) {
        $request->validate(["rsvp" => "required|in:accepted,declined,tentative"]);
        $emp = Employee::where("user_id",auth()->id())->first();
        if ($emp) $meeting->participants()->updateExistingPivot($emp->id, ["rsvp" => $request->rsvp]);
        return back()->with("success","RSVP updated.");
    }
    public function saveMinutes(Request $request, Meeting $meeting) {
        $meeting->update(["minutes" => $request->validate(["minutes" => "required|string"])["minutes"], "status" => "completed"]);
        return back()->with("success","Minutes saved.");
    }
    public function cancel(Request $request, Meeting $meeting) {
        $meeting->update(["status" => "cancelled"]);
        return back()->with("success","Meeting cancelled.");
    }
    public function calendar() { return view("meetings.calendar"); }
    public function calendarEvents(Request $request) {
        $meetings = Meeting::where("status","!=","cancelled")->orderBy("start_at")->get()
            ->map(fn($m) => ["id" => $m->id, "title" => $m->title, "start" => $m->start_at, "end" => $m->end_at, "color" => "#3b82f6", "url" => route("meetings.show",$m)]);
        return response()->json($meetings);
    }
}
