<?php
namespace App\Console\Commands;

use App\Mail\MeetingInviteMail;
use App\Models\{Meeting, MeetingParticipant, Employee};
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class GenerateRecurringMeetings extends Command
{
    protected $signature   = 'hrms:generate-recurring-meetings';
    protected $description = 'Spawn next instance of recurring meetings that occur tomorrow.';

    public function handle(): void
    {
        $tomorrow = Carbon::tomorrow();

        // Find parent recurring meetings whose next occurrence falls on tomorrow
        // and haven't been ended yet
        $parents = Meeting::whereNotNull('recurrence')
            ->whereNull('parent_meeting_id')
            ->where(function ($q) use ($tomorrow) {
                $q->whereNull('recurrence_end_date')
                  ->orWhere('recurrence_end_date', '>=', $tomorrow->toDateString());
            })
            ->get();

        $generated = 0;

        foreach ($parents as $parent) {
            $nextStart = $this->nextOccurrence($parent, $tomorrow);

            if (!$nextStart) continue;

            // Check we haven't already created this instance
            $alreadyExists = Meeting::where('parent_meeting_id', $parent->id)
                ->whereDate('start_at', $nextStart->toDateString())
                ->exists();

            if ($alreadyExists) continue;

            $duration   = $parent->start_at->diffInMinutes($parent->end_at);
            $nextEnd    = $nextStart->copy()->addMinutes($duration);

            $instance = Meeting::create([
                'title'            => $parent->title,
                'organizer_id'     => $parent->organizer_id,
                'start_at'         => $nextStart,
                'end_at'           => $nextEnd,
                'location'         => $parent->location,
                'description'      => $parent->description,
                'status'           => 'scheduled',
                'parent_meeting_id'=> $parent->id,
            ]);

            // Copy participants
            $parentParticipants = $parent->participants()->pluck('employee_id');
            foreach ($parentParticipants as $empId) {
                MeetingParticipant::create(['meeting_id' => $instance->id, 'employee_id' => $empId, 'rsvp' => 'pending']);
            }

            // Send invite emails
            $employees = Employee::with('user')->whereIn('id', $parentParticipants)->get();
            foreach ($employees as $employee) {
                if ($employee->user?->email) {
                    Mail::to($employee->user->email)->queue(new MeetingInviteMail($instance, $employee));
                }
            }

            $generated++;
        }

        $this->info("Generated {$generated} recurring meeting instance(s) for " . $tomorrow->toDateString());
    }

    private function nextOccurrence(Meeting $parent, Carbon $target): ?Carbon
    {
        $base = $parent->start_at->copy();

        // Build candidate dates based on recurrence pattern until we reach target
        $candidate = $base->copy();
        $step = match ($parent->recurrence) {
            'daily'     => fn($d) => $d->addDay(),
            'weekly'    => fn($d) => $d->addWeek(),
            'biweekly'  => fn($d) => $d->addWeeks(2),
            'monthly'   => fn($d) => $d->addMonth(),
            default     => null,
        };

        if (!$step) return null;

        // Advance until candidate == target date
        $limit = 0;
        while ($candidate->lt($target) && $limit++ < 1000) {
            $step($candidate);
        }

        if ($candidate->toDateString() === $target->toDateString()) {
            return $candidate;
        }

        return null;
    }
}
