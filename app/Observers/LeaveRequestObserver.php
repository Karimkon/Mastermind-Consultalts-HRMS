<?php
namespace App\Observers;

use App\Mail\LeaveStatusMail;
use App\Models\AuditLog;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Mail;

class LeaveRequestObserver
{
    public function created(LeaveRequest $leave): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'created',
            'model_type' => 'LeaveRequest',
            'model_id'   => $leave->id,
            'new_values' => json_encode($leave->toArray()),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function updated(LeaveRequest $leave): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'updated',
            'model_type' => 'LeaveRequest',
            'model_id'   => $leave->id,
            'old_values' => json_encode($leave->getOriginal()),
            'new_values' => json_encode($leave->getDirty()),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Email employee when status changes to approved or rejected
        if ($leave->isDirty('status') && in_array($leave->status, ['approved','rejected'])) {
            $email = $leave->employee?->user?->email;
            if ($email) {
                Mail::to($email)->queue(new LeaveStatusMail($leave->fresh(['employee.user','leaveType'])));
            }
        }
    }
}
