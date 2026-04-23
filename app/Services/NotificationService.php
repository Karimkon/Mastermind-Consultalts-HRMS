<?php
namespace App\Services;

use App\Mail\LeaveStatusMail;
use App\Mail\PayrollProcessedMail;
use App\Mail\TrainingEnrollmentMail;
use App\Models\{Notification, User, LeaveRequest, Payslip, TrainingEnrollment, Meeting, JobPosting};
use Illuminate\Support\Facades\Mail;

class NotificationService
{
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

        User::role(['hr-admin', 'super-admin', 'manager'])->each(function (User $u) use ($leave, $name) {
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

        User::role(['recruiter', 'hr-admin', 'super-admin'])->each(function (User $u) use ($candidate, $jobTitle) {
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
