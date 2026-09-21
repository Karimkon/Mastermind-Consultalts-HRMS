<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Changing a leave that has already been granted.
 *
 * Somebody is recalled early, or asks for two more days. The dates move, the
 * days are recounted, and the balance moves by exactly the difference — never
 * recomputed from scratch, because a balance carries other leave besides this
 * request.
 */
class LeaveAdjustmentService
{
    /**
     * Working days between two dates, counted the way the application form
     * counts them so an adjusted request never disagrees with how it was
     * originally sized.
     */
    public function workingDays(Carbon $from, Carbon $to): float
    {
        if ($to->lt($from)) {
            return 0;
        }

        return $from->copy()->diffInWeekdays($to) + 1;
    }

    /** Entitlement, what is spent, and what is left. */
    public function balanceFor(Employee $employee, int $leaveTypeId, ?int $year = null): array
    {
        $year = $year ?: (int) now()->year;
        $type = LeaveType::find($leaveTypeId);

        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        // total_days on the row is the entitlement actually granted to this
        // person; the leave type's allowance is the fallback when no row has
        // been seeded yet.
        $entitled = (float) ($balance?->total_days ?? $type?->days_allowed ?? 0);
        $used     = (float) ($balance?->used_days ?? 0);
        $pending  = (float) ($balance?->pending_days ?? 0);

        return [
            'entitled'  => $entitled,
            'used'      => $used,
            'pending'   => $pending,
            'remaining' => round($entitled - $used - $pending, 2),
            'seeded'    => (bool) $balance,
        ];
    }

    /**
     * Move the balance by a difference in days.
     *
     * An approved leave is spent, so it moves `used_days`; one still waiting is
     * only reserved, so it moves `pending_days`. Nothing is ever recomputed
     * from the request alone — the same balance holds every other leave the
     * person has taken this year.
     */
    public function shiftBalance(LeaveRequest $leave, float $delta): void
    {
        if (abs($delta) < 0.001) {
            return;
        }

        $balance = LeaveBalance::firstOrCreate(
            [
                'employee_id'   => $leave->employee_id,
                'leave_type_id' => $leave->leave_type_id,
                'year'          => (int) now()->year,
            ],
            [
                'total_days'   => LeaveType::find($leave->leave_type_id)?->days_allowed ?? 0,
                'used_days'    => 0,
                'pending_days' => 0,
            ]
        );

        $column = $leave->status === 'approved' ? 'used_days' : 'pending_days';

        // Never below zero: a balance that reads -2 days is worse than one that
        // reads 0, because somebody will believe it.
        $balance->$column = max(0, round((float) $balance->$column + $delta, 2));
        $balance->save();
    }

    /**
     * Change how long somebody is away.
     *
     * Returns the new day count. The balance moves by the difference only.
     */
    public function changeEndDate(LeaveRequest $leave, Carbon $newTo, ?string $note = null): float
    {
        $from = Carbon::parse($leave->from_date);
        $oldDays = (float) $leave->days_count;
        $newDays = $this->workingDays($from, $newTo);

        DB::transaction(function () use ($leave, $newTo, $oldDays, $newDays, $note) {
            // Recorded once, so it always holds what was actually approved
            // rather than the last adjustment.
            if (! $leave->original_to_date) {
                $leave->original_to_date = $leave->to_date;
                $leave->original_days = $oldDays;
            }

            $leave->to_date = $newTo->toDateString();
            $leave->days_count = $newDays;

            if ($note) {
                $leave->adjustment_note = trim(($leave->adjustment_note ? $leave->adjustment_note."\n" : '').$note);
            }

            $leave->save();

            $this->shiftBalance($leave, $newDays - $oldDays);
        });

        $this->audit($leave, 'leave_adjusted', $oldDays, $newDays);

        return $newDays;
    }

    /**
     * Bring somebody back now.
     *
     * The leave ends today; the days from tomorrow onwards are given back.
     * If it has not started yet the whole thing is cancelled instead, because
     * a leave ending before it begins is not a shortened leave.
     */
    public function recall(LeaveRequest $leave, ?string $note = null): array
    {
        $from = Carbon::parse($leave->from_date);
        $today = now()->startOfDay();
        $oldDays = (float) $leave->days_count;

        if ($from->gt($today)) {
            DB::transaction(function () use ($leave, $oldDays, $note) {
                $this->shiftBalance($leave, -$oldDays);

                $leave->status = 'cancelled';
                $leave->recalled_at = now();
                $leave->recalled_by = auth()->id();
                $leave->adjustment_note = trim(($leave->adjustment_note ? $leave->adjustment_note."\n" : '')
                    .($note ?: 'Recalled before the leave started, so it was cancelled.'));
                $leave->save();

                $leave->employee?->update(['status' => 'active']);
            });

            $this->audit($leave, 'leave_recalled', $oldDays, 0);

            return ['cancelled' => true, 'days' => 0, 'returned' => $oldDays];
        }

        $newDays = $this->workingDays($from, $today);

        DB::transaction(function () use ($leave, $today, $oldDays, $newDays, $note) {
            if (! $leave->original_to_date) {
                $leave->original_to_date = $leave->to_date;
                $leave->original_days = $oldDays;
            }

            $leave->to_date = $today->toDateString();
            $leave->days_count = $newDays;
            $leave->recalled_at = now();
            $leave->recalled_by = auth()->id();

            if ($note) {
                $leave->adjustment_note = trim(($leave->adjustment_note ? $leave->adjustment_note."\n" : '').$note);
            }

            $leave->save();

            $this->shiftBalance($leave, $newDays - $oldDays);

            // Back on duty from tomorrow, so the register stops showing them away.
            $leave->employee?->update(['status' => 'active']);
        });

        $this->audit($leave, 'leave_recalled', $oldDays, $newDays);

        return ['cancelled' => false, 'days' => $newDays, 'returned' => round($oldDays - $newDays, 2)];
    }

    private function audit(LeaveRequest $leave, string $action, float $oldDays, float $newDays): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => 'LeaveRequest',
            'model_id'   => $leave->id,
            'old_values' => json_encode(['days_count' => $oldDays, 'to_date' => (string) $leave->original_to_date]),
            'new_values' => json_encode(['days_count' => $newDays, 'to_date' => (string) $leave->to_date]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
