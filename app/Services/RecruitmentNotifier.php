<?php

namespace App\Services;

use App\Mail\ApplicationStatusMail;
use App\Mail\NewJobAlertMail;
use App\Models\Candidate;
use App\Models\CandidateStatusEvent;
use App\Models\JobPosting;
use App\Models\JobSeeker;
use App\Models\JobSeekerNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells applicants things, on whatever channels are actually available.
 *
 * Each method returns the channels that genuinely went out, never the ones
 * that were attempted. The caller writes that onto the trail, so a status
 * event that says "app,email" means those two happened and SMS did not.
 */
class RecruitmentNotifier
{
    public function __construct(private SmsService $sms) {}

    /**
     * An application changed stage. In-app for account holders, email for
     * everybody with an address, SMS when a gateway is configured.
     *
     * @return string[] the channels that were delivered
     */
    public function applicationStatusChanged(Candidate $candidate, CandidateStatusEvent $event): array
    {
        $candidate->loadMissing(['jobPosting', 'jobSeeker', 'statusEvents']);
        $sent = [];

        // In-app, only if they have an account. Somebody who applied from the
        // public page has nowhere for this to land, and that is fine - they
        // get the email and the tracking link.
        if ($candidate->job_seeker_id) {
            try {
                JobSeekerNotification::create([
                    'job_seeker_id' => $candidate->job_seeker_id,
                    'type'          => 'application_status',
                    'title'         => $this->titleFor($event->to_status, $candidate),
                    'body'          => $event->message,
                    'data'          => [
                        'candidate_id'  => $candidate->id,
                        'job_id'        => $candidate->job_posting_id,
                        'status'        => $event->to_status,
                        'tracking_code' => $candidate->tracking_code,
                    ],
                    'action_url'    => '/applications/' . $candidate->id,
                ]);
                $sent[] = 'app';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (filled($candidate->email)) {
            try {
                $progress = app(RecruitmentPipeline::class)->progressFor($candidate);
                Mail::to($candidate->email)->queue(new ApplicationStatusMail($candidate, $event, $progress));
                $sent[] = 'email';
            } catch (\Throwable $e) {
                // A broken address on one application must not stop the status
                // change the recruiter just made.
                Log::warning('Application status email not queued', [
                    'candidate' => $candidate->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        // SMS carries the short version. The full wording is in the email.
        if (filled($candidate->phone)) {
            $text = $this->smsTextFor($candidate, $event);
            if ($this->sms->send($candidate->phone, $text)) $sent[] = 'sms';
        }

        return $sent;
    }

    /**
     * A job opened. Tell every seeker who asked about that category.
     *
     * @return int how many seekers were reached on at least one channel
     */
    public function newJobPosted(JobPosting $job): int
    {
        $job->loadMissing('category');
        if (! $job->job_category_id) return 0;      // nothing to match on
        if ($job->status !== 'open' || ! $job->is_public) return 0;

        $reached = 0;

        JobSeeker::query()
            ->whereHas('categories', fn ($q) => $q->where('job_categories.id', $job->job_category_id))
            ->chunkById(200, function ($seekers) use ($job, &$reached) {
                foreach ($seekers as $seeker) {
                    if ($this->alertOne($seeker, $job)) $reached++;
                }
            });

        return $reached;
    }

    /** One seeker, one job. True when at least one channel delivered. */
    private function alertOne(JobSeeker $seeker, JobPosting $job): bool
    {
        $any = false;

        try {
            JobSeekerNotification::create([
                'job_seeker_id' => $seeker->id,
                'type'          => 'new_job',
                'title'         => 'New vacancy: ' . $job->title,
                'body'          => trim(($job->category?->name ? $job->category->name . ' - ' : '')
                    . ($job->location ?: 'Location not specified')),
                'data'          => ['job_id' => $job->id, 'slug' => $job->slug],
                'action_url'    => '/jobs/' . $job->id,
            ]);
            $any = true;
        } catch (\Throwable $e) {
            report($e);
        }

        if ($seeker->notify_email && filled($seeker->email)) {
            try {
                Mail::to($seeker->email)->queue(new NewJobAlertMail($seeker, $job));
                $any = true;
            } catch (\Throwable $e) {
                Log::warning('New job alert email not queued', [
                    'seeker' => $seeker->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        if ($seeker->notify_sms && filled($seeker->phone)) {
            $text = "Mastermind: new vacancy - {$job->title}"
                . ($job->location ? " in {$job->location}" : '')
                . '. Apply on the Mastermind Careers app.';
            if ($this->sms->send($seeker->phone, $text)) $any = true;
        }

        return $any;
    }

    private function titleFor(string $status, Candidate $candidate): string
    {
        $job = $candidate->jobPosting?->title ?? 'your application';

        return match ($status) {
            'new'         => 'Application received',
            'screening'   => 'Your application is being screened',
            'shortlisted' => 'You have been shortlisted',
            'interview'   => 'Interview invitation',
            'offer'       => 'Job offer: ' . $job,
            'hired'       => 'Appointment confirmed',
            'rejected'    => 'Application outcome',
            default       => 'Application update',
        };
    }

    /**
     * The SMS version. One segment where possible, because a gateway charges
     * per 160 characters and there are a lot of applicants.
     */
    private function smsTextFor(Candidate $candidate, CandidateStatusEvent $event): string
    {
        $job  = $candidate->jobPosting?->title ?? 'your application';
        $code = $candidate->tracking_code;

        $body = match ($event->to_status) {
            'new'         => "Application received for {$job}. Ref {$code}.",
            'screening'   => "Your application for {$job} is being screened. Ref {$code}.",
            'shortlisted' => "Good news: you are SHORTLISTED for {$job}. Ref {$code}.",
            'interview'   => "You are invited to interview for {$job}. Ref {$code}.",
            'offer'       => "You have been offered the {$job} position. Ref {$code}.",
            'hired'       => "Your appointment for {$job} is confirmed. Ref {$code}.",
            'rejected'    => "Thank you for applying for {$job}. You were not shortlisted this time. Ref {$code}.",
            default       => "Update on your application for {$job}. Ref {$code}.",
        };

        return 'Mastermind: ' . $body;
    }
}
