<?php
namespace App\Services;

use App\Mail\LeaveStatusMail;
use App\Mail\PayrollProcessedMail;
use App\Mail\TrainingEnrollmentMail;
use App\Models\{Notification, User, LeaveRequest, Payslip, TrainingEnrollment, Meeting, JobPosting};
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * The users holding any of these roles, skipping names that are not real
     * roles on this installation.
     *
     * Spatie's User::role() throws on an unknown name. A role nobody has
     * created yet is simply an audience with no members; the people who do
     * hold the other roles must still be told.
     */
    private function usersWithAnyRole(array $roles)
    {
        $known = \Spatie\Permission\Models\Role::whereIn('name', $roles)->pluck('name')->all();
        if (! $known) return collect();

        return User::role($known)->get()->unique('id');
    }
    // ──────────────────────────────────────────────
    // LEAVE
    // ──────────────────────────────────────────────

    /**
     * Leave submitted by employee → notify all HR / managers.
     */
    public function leaveSubmitted(LeaveRequest $leave): void
    {
        $leave->loadMissing(['employee.department', 'leaveType']);
        $emp  = $leave->employee;
        $name = $emp ? "{$emp->first_name} {$emp->last_name}" : 'An employee';

        $this->usersWithAnyRole(['hr-admin', 'super-admin', 'manager'])->each(function (User $u) use ($leave, $name) {
            Notification::create([
                'user_id' => $u->id,
                'type'    => 'leave_submitted',
                'title'   => 'New Leave Request',
                'body'    => "{$name} submitted a {$leave->leaveType?->name} request ({$leave->days_count} day(s)).",
                'data'    => ['leave_id' => $leave->id],
            ]);

            if ($u->email) {
                Mail::to($u->email)->queue(new \App\Mail\LeaveSubmittedMail($leave));
            }
        });
    }

    /**
     * Leave approved or rejected → notify the requesting employee.
     */
    public function leaveStatusChanged(LeaveRequest $leave): void
    {
        $leave->loadMissing(['employee.user', 'leaveType']);
        $user = $leave->employee?->user;
        if (! $user) return;

        $statusLabel = ucfirst($leave->status);

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'leave_status',
            'title'   => "Leave Request {$statusLabel}",
            'body'    => "Your {$leave->leaveType?->name} leave ({$leave->days_count} day(s)) has been {$leave->status}.",
            'data'    => ['leave_id' => $leave->id],
        ]);

        if ($user->email) {
            Mail::to($user->email)->queue(new LeaveStatusMail($leave));
        }
    }

    // ──────────────────────────────────────────────
    // PAYROLL
    // ──────────────────────────────────────────────

    /**
     * Payroll processed → notify each employee with a payslip.
     */
    public function payrollProcessed(Payslip $payslip): void
    {
        $payslip->loadMissing(['employee.user', 'payrollRun']);
        $user = $payslip->employee?->user;
        if (! $user) return;

        $title = $payslip->payrollRun?->title ?? 'Payroll';

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'payroll_processed',
            'title'   => 'Your Payslip is Ready',
            'body'    => "Your payslip for {$title} is now available. Net: UGX " . number_format($payslip->net_salary, 0),
            'data'    => ['payslip_id' => $payslip->id],
        ]);

        if ($user->email) {
            Mail::to($user->email)->queue(new PayrollProcessedMail($payslip));
        }
    }

    /**
     * Roles that own each stage of the payroll approval chain.
     *
     * super-admin sits on every stage because it can act at any of them, and a
     * run that nobody is told about is a run that stops moving.
     */
    private const PAYROLL_STAGE_ROLES = [
        'hr'      => ['hr-admin', 'super-admin'],
        'finance' => ['payroll-officer', 'super-admin'],
        'md'      => ['md', 'super-admin'],
        'payment' => ['payroll-officer', 'super-admin'],
        'am'      => ['account-manager', 'super-admin'],
    ];

    private const PAYROLL_STAGE_COPY = [
        'hr'      => ['Payroll awaiting HR approval',      'has been processed and is waiting for HR to review the employees and approve it.'],
        'finance' => ['Payroll awaiting Finance approval', 'has been approved by HR and is waiting for Finance to review and approve it.'],
        'md'      => ['Payroll awaiting MD approval',      'has been approved by Finance and is waiting for the MD to give final approval.'],
        'payment' => ['Payroll approved — ready to pay',   'has been given final approval by the MD and is locked. Finance can now mark it as paid.'],
        'am'      => ['Payroll sent back to you',          'has been sent back for correction.'],
    ];

    /**
     * Payroll reached a new stage → tell whoever has to act on it next.
     *
     * @param string $stage one of the keys in self::PAYROLL_STAGE_ROLES
     */
    public function payrollAwaitingStage(\App\Models\PayrollRun $run, string $stage): void
    {
        [$heading, $tail] = self::PAYROLL_STAGE_COPY[$stage] ?? [null, null];
        if (! $heading) return;

        $action = "\"{$run->title}\" {$tail}";

        $this->notifyPayrollRoles($run, self::PAYROLL_STAGE_ROLES[$stage], $heading, $action);
    }

    /**
     * A run was pushed back down the chain → tell the stage that now owns it,
     * carrying the reason so the correction can actually be made.
     */
    public function payrollSentBack(\App\Models\PayrollRun $run, string $stage, string $comment, ?string $byName = null): void
    {
        $heading = 'Payroll sent back for correction';
        $action  = "\"{$run->title}\" has been sent back to you" . ($byName ? " by {$byName}" : '') . '.';

        $this->notifyPayrollRoles(
            $run,
            self::PAYROLL_STAGE_ROLES[$stage] ?? self::PAYROLL_STAGE_ROLES['am'],
            $heading,
            $action,
            $comment,
            $byName,
        );
    }

    /**
     * Shared delivery for the payroll chain: a bell for everyone who can act,
     * plus an email, because approvers are not sitting in the system all day.
     */
    private function notifyPayrollRoles(
        \App\Models\PayrollRun $run,
        array $roles,
        string $heading,
        string $action,
        ?string $comment = null,
        ?string $byName = null,
    ): void {
        $this->usersWithAnyRole($roles)->each(function (User $u) use ($run, $heading, $action, $comment, $byName) {
            Notification::create([
                'user_id' => $u->id,
                'type'    => 'payroll_stage',
                'title'   => $heading,
                'body'    => $action . ($comment ? " Reason: {$comment}" : ''),
                'data'    => ['payroll_run_id' => $run->id],
            ]);

            // One approver's mail server being down must not roll back the
            // approval that has already been recorded.
            if ($u->email) {
                try {
                    Mail::to($u->email)->queue(new \App\Mail\PayrollStageMail($run, $heading, $action, $comment, $byName));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

    // ──────────────────────────────────────────────
    // MEETINGS
    // ──────────────────────────────────────────────

    /**
     * Meeting created → notify each participant (in-app only; email already sent in controller).
     */
    public function meetingInvite(Meeting $meeting, \App\Models\Employee $employee): void
    {
        $user = $employee->user;
        if (! $user) return;

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'meeting_invite',
            'title'   => 'Meeting Invitation',
            'body'    => "You have been invited to: {$meeting->title} on " . \Carbon\Carbon::parse($meeting->start_at)->format('M d, Y H:i'),
            'data'    => ['meeting_id' => $meeting->id],
        ]);
    }

    // ──────────────────────────────────────────────
    // TRAINING
    // ──────────────────────────────────────────────

    /**
     * Employee enrolled in training → in-app + email.
     */
    public function trainingEnrolled(TrainingEnrollment $enrollment): void
    {
        $enrollment->loadMissing(['employee.user', 'course']);
        $user = $enrollment->employee?->user;
        if (! $user) return;

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'training_enrolled',
            'title'   => 'Training Enrollment',
            'body'    => "You have been enrolled in: {$enrollment->course->title}.",
            'data'    => ['course_id' => $enrollment->course_id],
        ]);

        if ($user->email) {
            Mail::to($user->email)->queue(new TrainingEnrollmentMail($enrollment));
        }
    }

    // ──────────────────────────────────────────────
    // RECRUITMENT
    // ──────────────────────────────────────────────

    /**
     * New job application from public careers page → notify all recruiters.
     */
    public function newApplication(\App\Models\Candidate $candidate): void
    {
        $candidate->loadMissing('jobPosting');
        $jobTitle = $candidate->jobPosting?->title ?? 'a job';

        $this->usersWithAnyRole(['recruiter', 'hr-admin', 'super-admin'])->each(function (User $u) use ($candidate, $jobTitle) {
            Notification::create([
                'user_id' => $u->id,
                'type'    => 'new_application',
                'title'   => 'New Job Application',
                'body'    => "{$candidate->first_name} {$candidate->last_name} applied for {$jobTitle}.",
                'data'    => ['candidate_id' => $candidate->id, 'job_id' => $candidate->job_posting_id],
            ]);

            if ($u->email) {
                Mail::to($u->email)->queue(new \App\Mail\JobApplicationMail($candidate));
            }
        });
    }

    // ──────────────────────────────────────────────
    // PERFORMANCE
    // ──────────────────────────────────────────────

    /**
     * Performance review submitted → notify manager.
     */
    public function performanceReviewSubmitted(\App\Models\PerformanceReview $review): void
    {
        $review->loadMissing(['employee', 'reviewer']);

        // Notify the reviewer (manager) if different from the employee
        $reviewerUser = $review->reviewer?->user;
        if ($reviewerUser && $reviewerUser->id !== $review->employee?->user_id) {
            Notification::create([
                'user_id' => $reviewerUser->id,
                'type'    => 'review_submitted',
                'title'   => 'Performance Review Submitted',
                'body'    => "{$review->employee?->first_name} {$review->employee?->last_name} submitted their self-evaluation.",
                'data'    => ['review_id' => $review->id],
            ]);
        }
    }
}
