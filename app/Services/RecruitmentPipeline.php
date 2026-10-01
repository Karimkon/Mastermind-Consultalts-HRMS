<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateStatusEvent;
use Illuminate\Support\Facades\DB;

/**
 * The recruitment flow: the stages, the wording, and the single door every
 * status change goes through.
 *
 * Before this, a candidate status was changed by whichever controller felt
 * like it and the applicant was told nothing. Everything now routes through
 * moveTo(), which writes the trail, picks the preset message for the stage
 * and hands it to the notifier. That is what makes the applicant status page,
 * the client flow chart and the "do not tell them twice" rule all agree.
 */
class RecruitmentPipeline
{
    /**
     * The stages an applicant is shown, in order.
     *
     * Deliberately coarser than the internal status column. An applicant does
     * not need to know the difference between "offer" and "hired" to know
     * things went well, and the brief asked for this shape by name.
     */
    public const STAGES = [
        'applied' => [
            'label'    => 'Job Applied',
            'statuses' => ['new'],
            'blurb'    => 'We have your application and the documents you attached.',
        ],
        'screening' => [
            'label'    => 'Screening',
            'statuses' => ['screening'],
            'blurb'    => 'Your answers and documents are being checked against the requirements.',
        ],
        'shortlisting' => [
            'label'    => 'Shortlisting',
            'statuses' => ['shortlisted'],
            'blurb'    => 'You are on the shortlist for this position.',
        ],
        'interview' => [
            'label'    => 'Interview',
            'statuses' => ['interview'],
            'blurb'    => 'You have been invited to interview.',
        ],
        'outcome' => [
            'label'    => 'Outcome',
            'statuses' => ['offer', 'hired', 'rejected'],
            'blurb'    => 'A decision has been reached on your application.',
        ],
    ];

    /**
     * What the applicant is told at each internal status.
     *
     * Preset so the wording is the same for everybody and nobody has to
     * compose a rejection at four in the afternoon. {name}, {job} and {code}
     * are filled in; anything else is left alone.
     */
    public const MESSAGES = [
        'new' => 'Dear {name}, we have received your application for {job}. Your reference is {code}. You can follow your application using that reference.',
        'screening' => 'Dear {name}, your application for {job} is now being screened. Reference {code}.',
        'shortlisted' => 'Dear {name}, congratulations. You have been shortlisted for {job}. We will contact you with the next step. Reference {code}.',
        'interview' => 'Dear {name}, you have been invited to interview for {job}. We will confirm the date and time shortly. Reference {code}.',
        'offer' => 'Dear {name}, we are pleased to offer you the position of {job}. Our team will be in touch with the details. Reference {code}.',
        'hired' => 'Dear {name}, welcome to Mastermind Consultants. Your appointment for {job} is confirmed. Reference {code}.',
        'rejected' => 'Dear {name}, thank you for applying for {job}. On this occasion you have not been shortlisted. We will keep your details for future openings. Reference {code}.',
    ];

    /** Statuses that count as a decision, where there is nothing further to wait for. */
    public const TERMINAL = ['hired', 'rejected'];

    public function __construct(private RecruitmentNotifier $notifier) {}

    /** Which public stage a given internal status sits in. */
    public function stageOf(string $status): string
    {
        foreach (self::STAGES as $key => $stage) {
            if (in_array($status, $stage['statuses'], true)) return $key;
        }

        return 'applied';
    }

    /**
     * The stage list for one candidate, each marked done / current / pending.
     *
     * This is the applicant status view and the client flow chart, which are
     * the same thing drawn differently.
     */
    public function progressFor(Candidate $candidate): array
    {
        $keys    = array_keys(self::STAGES);
        $current = $this->stageOf($candidate->status);
        $at      = array_search($current, $keys, true);
        $reached = $candidate->statusEvents->pluck('to_status')
            ->map(fn ($s) => $this->stageOf((string) $s))->unique()->all();

        $out = [];
        foreach ($keys as $i => $key) {
            $stage = self::STAGES[$key];

            // The outcome stage is only "done" when the outcome is final;
            // an offer that nobody has accepted is still in play.
            $state = match (true) {
                $i <   $at => 'done',
                $i === $at => in_array($candidate->status, self::TERMINAL, true) ? 'done' : 'current',
                default    => in_array($key, $reached, true) ? 'done' : 'pending',
            };

            $label = $stage['label'];
            if ($key === 'outcome' && $state !== 'pending') {
                $label = $candidate->status === 'rejected' ? 'Not Shortlisted' : 'Successful';
            }

            $out[] = [
                'key'   => $key,
                'label' => $label,
                'blurb' => $stage['blurb'],
                'state' => $state,
            ];
        }

        return $out;
    }

    /** The preset message for a status, with the applicant details filled in. */
    public function messageFor(Candidate $candidate, string $status): string
    {
        $template = self::MESSAGES[$status]
            ?? 'Dear {name}, there is an update on your application for {job}. Reference {code}.';

        return strtr($template, [
            '{name}' => $candidate->name ?: 'Applicant',
            '{job}'  => $candidate->jobPosting?->title ?? 'the position you applied for',
            '{code}' => $candidate->tracking_code ?? '',
        ]);
    }

    /**
     * Move an application to a new status and tell the applicant.
     *
     * Returns the event, or null when the status did not actually change -
     * saving a candidate form twice must not send a second SMS.
     *
     * $message overrides the preset for this one move, which is how a
     * recruiter adds a line of their own without losing the trail.
     */
    public function moveTo(
        Candidate $candidate,
        string $status,
        ?string $message = null,
        bool $notify = true,
        ?int $actorId = null,
    ): ?CandidateStatusEvent {
        $from = $candidate->status;
        if ($from === $status) return null;

        $candidate->loadMissing('jobPosting');
        $body = $message ?: $this->messageFor($candidate, $status);

        $event = DB::transaction(function () use ($candidate, $from, $status, $body, $actorId) {
            $candidate->forceFill(['status' => $status])->save();

            return CandidateStatusEvent::create([
                'candidate_id' => $candidate->id,
                'from_status'  => $from,
                'to_status'    => $status,
                'message'      => $body,
                'created_by'   => $actorId ?? auth()->id(),
            ]);
        });

        // Notifying is outside the transaction on purpose. A mail queue or an
        // SMS gateway having a bad day must not roll back a status the
        // recruiter has already been told was saved.
        if ($notify) {
            $channels = $this->notifier->applicationStatusChanged($candidate, $event);
            $event->forceFill(['channels' => implode(',', $channels)])->save();
            $candidate->forceFill(['notified_status' => $status])->save();
        }

        return $event;
    }

    /**
     * Record the first event for a brand new application.
     *
     * Not moveTo(): the status did not change, the row was born at "new", and
     * the applicant still needs the acknowledgement carrying their reference.
     */
    public function recordApplied(Candidate $candidate, bool $notify = true): CandidateStatusEvent
    {
        $candidate->loadMissing('jobPosting');

        $event = CandidateStatusEvent::create([
            'candidate_id' => $candidate->id,
            'from_status'  => null,
            'to_status'    => $candidate->status,
            'message'      => $this->messageFor($candidate, 'new'),
        ]);

        if ($notify) {
            $channels = $this->notifier->applicationStatusChanged($candidate, $event);
            $event->forceFill(['channels' => implode(',', $channels)])->save();
            $candidate->forceFill(['notified_status' => $candidate->status])->save();
        }

        return $event;
    }
}
