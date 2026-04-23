<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Meeting, MeetingParticipant};
use Illuminate\Http\Request;

class MeetingApiController extends Controller
{
    public function index(Request $request)
    {
        $empId = $request->user()->employee?->id;

        $query = Meeting::with('organizer');

        if ($empId) {
            $query->where(fn($q) => $q
                ->where('organizer_id', $empId)
                ->orWhereHas('participants', fn($q2) => $q2->where('employee_id', $empId))
            );
        }

        if ($request->from) $query->whereDate('start_at', '>=', $request->from);
        if ($request->to)   $query->whereDate('start_at', '<=', $request->to);

        return response()->json([
            'data' => $query->orderBy('start_at', 'desc')->paginate(20)->through(fn($m) => $this->formatMeeting($m)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'    => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at'   => 'required|date|after:start_at',
        ]);

        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        $meeting = Meeting::create(
            $request->only(['title','description','location','start_at','end_at','type','meeting_url','agenda']) +
            ['organizer_id' => $employee->id, 'status' => 'scheduled']
        );

        if ($request->participant_ids) {
            foreach ($request->participant_ids as $eid) {
                MeetingParticipant::firstOrCreate(['meeting_id' => $meeting->id, 'employee_id' => $eid]);
            }
        }

        return response()->json(['data' => $this->formatMeeting($meeting->load('organizer'))], 201);
    }

    public function show(Meeting $meeting)
    {
        $meeting->load(['organizer', 'participants.employee.user']);
        $data = $this->formatMeeting($meeting);
        $data['participants'] = $meeting->participants->map(fn($p) => [
            'id'     => $p->employee?->id,
            'name'   => $p->employee?->full_name,
            'avatar' => $p->employee?->user?->avatar_url,
            'rsvp'   => $p->rsvp,
        ]);
        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $meeting->update($request->only(['title','description','location','start_at','end_at','type','meeting_url','agenda','minutes','status']));
        return response()->json(['data' => $this->formatMeeting($meeting->fresh()->load('organizer'))]);
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function rsvp(Request $request, Meeting $meeting)
    {
        $request->validate(['rsvp' => 'required|in:accepted,declined,tentative']);
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        MeetingParticipant::updateOrCreate(
            ['meeting_id' => $meeting->id, 'employee_id' => $employee->id],
            ['rsvp' => $request->rsvp]
        );
        return response()->json(['data' => ['rsvp' => $request->rsvp]]);
    }

    public function calendar(Request $request)
    {
        $empId = $request->user()->employee?->id;
        $from  = $request->from ?? now()->startOfMonth()->toDateString();
        $to    = $request->to   ?? now()->endOfMonth()->toDateString();

        $query = Meeting::with('organizer')
            ->whereDate('start_at', '>=', $from)
            ->whereDate('start_at', '<=', $to);

        if ($empId) {
            $query->where(fn($q) => $q
                ->where('organizer_id', $empId)
                ->orWhereHas('participants', fn($q2) => $q2->where('employee_id', $empId))
            );
        }

        return response()->json([
            'data' => $query->orderBy('start_at')->get()->map(fn($m) => [
                'id'       => $m->id,
                'title'    => $m->title,
                'start_at' => $m->start_at,
                'end_at'   => $m->end_at,
                'type'     => $m->type,
                'status'   => $m->status,
            ]),
        ]);
    }

    private function formatMeeting(Meeting $m): array
    {
        return [
            'id'              => $m->id,
            'title'           => $m->title,
            'description'     => $m->description,
            'location'        => $m->location,
            'meeting_url'     => $m->meeting_url,
            'start_at'        => $m->start_at,
            'end_at'          => $m->end_at,
            'type'            => $m->type,
            'status'          => $m->status ?? 'scheduled',
            'organizer'       => $m->organizer?->full_name,
            'participant_count' => $m->participants()->count(),
        ];
    }
}
