<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\CareersController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Recruitment\ShortlistingController;
use App\Jobs\ResolveApplicantOrigin;
use App\Models\{Candidate, CandidateDocument, JobCategory, JobPosting, JobSeeker, JobSeekerNotification, ShortlistingCriteria};
use App\Services\{NotificationService, QueueRunner, RecruitmentPipeline};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * The careers app: everything somebody outside the company can do.
 *
 * Nothing here touches `users`, employees, payroll or roles. A job seeker can
 * see public postings, their own applications and their own notifications,
 * and that is the whole surface.
 */
class CareersApiController extends Controller
{
    // ----------------------------------------------------------------
    // open to anybody
    // ----------------------------------------------------------------

    public function categories()
    {
        return response()->json([
            'categories' => JobCategory::active()->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'description']),
        ]);
    }

    /** The job list the app opens on. No account needed to look. */
    public function jobs(Request $request)
    {
        $jobs = JobPosting::with(['department:id,name', 'category:id,name'])
            ->withCount('candidates')
            ->where('status', 'open')
            ->where('is_public', true)
            ->when($request->category, fn ($q) => $q->where('job_category_id', $request->category))
            ->when($request->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$request->search}%")
                ->orWhere('location', 'like', "%{$request->search}%")))
            ->orderByDesc('created_at')
            ->paginate(20);

        $seeker = $request->user();
        $appliedTo = $seeker instanceof JobSeeker
            ? $seeker->applications()->pluck('job_posting_id')->all()
            : [];

        return response()->json([
            'jobs' => $jobs->getCollection()
                ->map(fn ($j) => $this->jobPayload($j, in_array($j->id, $appliedTo, true)))
                ->values(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page'    => $jobs->lastPage(),
                'total'        => $jobs->total(),
            ],
            'top_categories' => app(CareersController::class)->topCategories(),
        ]);
    }

    public function job(Request $request, JobPosting $job)
    {
        abort_unless($job->status === 'open' && $job->is_public, 404);
        $job->load(['department:id,name', 'category:id,name'])->loadCount('candidates');

        $criteria = ShortlistingCriteria::with('questions')
            ->where('job_posting_id', $job->id)->where('is_active', true)->latest()->first();

        $seeker  = $request->user();
        $applied = $seeker instanceof JobSeeker && $seeker->hasAppliedTo($job->id);

        return response()->json([
            'job' => $this->jobPayload($job, $applied) + [
                'description'  => $job->description,
                'requirements' => $job->requirements,
                'benefits'     => $job->benefits,
            ],
            'documents' => collect(CandidateDocument::TYPES)->map(fn ($m, $k) => [
                'type' => $k, 'label' => $m['label'],
                'required' => $m['required'], 'multiple' => $m['multiple'],
            ])->values(),
            'screening' => $criteria ? [
                'id'          => $criteria->id,
                'title'       => $criteria->title,
                'total_marks' => $criteria->maxScore(),
                'questions'   => $criteria->questions->map(fn ($q) => [
                    'id'       => $q->id,
                    'question' => $q->question,
                    'type'     => $q->question_type,
                    'marks'    => $q->isScored() ? (int) $q->weight : 0,
                    // Each answer carries what it is worth, so the app can
                    // show "5+ years - 5 marks" the way the web form does.
                    'options'  => collect($q->options ?? [])->map(fn ($o) => [
                        'text'  => $o['text'] ?? '',
                        'marks' => $o['marks'] ?? (($o['is_correct'] ?? false) ? (int) $q->weight : 0),
                    ])->values(),
                ])->values(),
            ] : null,
        ]);
    }

    /** Following an application by its reference, with no account. */
    public function track(string $code)
    {
        $candidate = Candidate::with(['jobPosting:id,title', 'statusEvents', 'shortlistingResponse'])
            ->where('tracking_code', $code)->firstOrFail();

        return response()->json(['application' => $this->applicationPayload($candidate, true)]);
    }

    // ----------------------------------------------------------------
    // accounts
    // ----------------------------------------------------------------

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:120',
            'email'            => 'required|email|max:255|unique:job_seekers,email',
            'phone'            => 'nullable|string|max:20',
            'password'         => ['required', 'confirmed', Password::min(8)],
            'location'         => 'nullable|string|max:120',
            'education_level'  => 'nullable|string|max:100',
            'experience_years' => 'nullable|integer|min:0|max:60',

            // Categories at registration, so the first new vacancy in their
            // line of work reaches them without them doing anything else.
            'categories'       => 'nullable|array|max:12',
            'categories.*'     => 'integer|exists:job_categories,id',
        ]);

        $seeker = DB::transaction(function () use ($data) {
            $seeker = JobSeeker::create($data);
            $seeker->categories()->sync($data['categories'] ?? []);
            return $seeker;
        });

        return response()->json([
            'token'  => $seeker->createToken('careers-app')->plainTextToken,
            'seeker' => $this->seekerPayload($seeker),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $seeker = JobSeeker::where('email', $data['email'])->first();

        if (! $seeker || ! Hash::check($data['password'], $seeker->password)) {
            return response()->json(['message' => 'That email and password do not match.'], 422);
        }

        return response()->json([
            'token'  => $seeker->createToken('careers-app')->plainTextToken,
            'seeker' => $this->seekerPayload($seeker),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request)
    {
        return response()->json(['seeker' => $this->seekerPayload($request->user())]);
    }

    public function updateMe(Request $request)
    {
        $seeker = $request->user();

        $data = $request->validate([
            'name'             => 'sometimes|string|max:120',
            'phone'            => 'nullable|string|max:20',
            'location'         => 'nullable|string|max:120',
            'education_level'  => 'nullable|string|max:100',
            'experience_years' => 'nullable|integer|min:0|max:60',
            'notify_email'     => 'sometimes|boolean',
            'notify_sms'       => 'sometimes|boolean',
            'categories'       => 'nullable|array|max:12',
            'categories.*'     => 'integer|exists:job_categories,id',
        ]);

        $seeker->update(collect($data)->except('categories')->all());

        // Only touched when the app actually sends the key, so a profile save
        // that leaves categories out does not silently unsubscribe somebody.
        if ($request->has('categories')) {
            $seeker->categories()->sync($data['categories'] ?? []);
        }

        return response()->json(['seeker' => $this->seekerPayload($seeker->fresh())]);
    }

    // ----------------------------------------------------------------
    // applying, from the app
    // ----------------------------------------------------------------

    /**
     * Apply for a posting as a signed-in job seeker.
     *
     * Same validation and the same storage as the public web form; the
     * difference is that the application is tied to an account, so the app
     * can show its progress and the in-app notices have somewhere to land.
     */
    public function apply(Request $request, JobPosting $job)
    {
        abort_unless($job->status === 'open' && $job->is_public, 404);

        $seeker = $request->user();

        if ($seeker->hasAppliedTo($job->id)) {
            $existing = $seeker->applications()->where('job_posting_id', $job->id)->first();
            return response()->json([
                'message'     => 'You have already applied for this position.',
                'application' => $this->applicationPayload($existing->load(['jobPosting:id,title', 'statusEvents', 'shortlistingResponse'])),
            ], 409);
        }

        $rules = ['cv' => 'required|file|max:8192|mimes:pdf,doc,docx'];
        foreach (CandidateDocument::TYPES as $type => $meta) {
            if ($type === 'cv') continue;
            $allowed = $type === 'passport_photo' ? 'jpg,jpeg,png' : 'pdf,doc,docx,jpg,jpeg,png';
            if ($meta['multiple']) {
                $rules["documents.{$type}"]   = 'nullable|array|max:6';
                $rules["documents.{$type}.*"] = "file|max:8192|mimes:{$allowed}";
            } else {
                $rules["documents.{$type}"] = "nullable|file|max:8192|mimes:{$allowed}";
            }
        }
        $rules['cover_letter'] = 'nullable|string|max:2000';
        $request->validate($rules);

        // The same rule as the careers page: a questionnaire that exists must
        // be completed. Checked before anything is written, so a half-filled
        // application never leaves a candidate row behind.
        CareersController::requireScreeningAnswers($request, CareersController::activeCriteria($job));

        $careers = app(CareersController::class);

        $candidate = DB::transaction(function () use ($request, $job, $seeker, $careers) {
            $parts = preg_split('/\s+/', trim($seeker->name), 2);

            $candidate = Candidate::create([
                'job_posting_id'   => $job->id,
                'job_seeker_id'    => $seeker->id,
                'first_name'       => $parts[0],
                'last_name'        => $parts[1] ?? '',
                'email'            => $seeker->email,
                'phone'            => $seeker->phone,
                'cover_letter'     => $request->input('cover_letter'),
                'status'           => 'new',
                'source'           => 'careers_app',
                'education_level'  => $seeker->education_level,
                'experience_years' => $seeker->experience_years,
                'ip_address'       => $request->ip(),
            ]);

            $cv = $careers->storeDocument($candidate, 'cv', $request->file('cv'));
            $candidate->forceFill(['resume_path' => $cv->path])->save();

            foreach (CandidateDocument::TYPES as $type => $meta) {
                if ($type === 'cv') continue;
                $input = $request->file("documents.{$type}");
                if (! $input) continue;
                foreach (is_array($input) ? $input : [$input] as $file) {
                    $careers->storeDocument($candidate, $type, $file);
                }
            }

            return $candidate;
        });

        // Screening answers arrive as screening[questionId] like the web form.
        $criteria = ShortlistingCriteria::with('questions')
            ->where('job_posting_id', $job->id)->where('is_active', true)->latest()->first();

        if ($criteria && $criteria->questions->isNotEmpty()) {
            $answers = [];
            foreach ($criteria->questions as $q) {
                $answers[$q->id] = $request->input("screening.{$q->id}");
            }
            ShortlistingController::saveResponses($candidate, $criteria, $answers);
        }

        app(NotificationService::class)->newApplication($candidate);
        app(RecruitmentPipeline::class)->recordApplied($candidate);

        ResolveApplicantOrigin::dispatch($candidate->id);
        QueueRunner::kick();

        $candidate->load(['jobPosting:id,title', 'statusEvents', 'shortlistingResponse', 'documents']);

        return response()->json([
            'message'     => 'Your application has been submitted.',
            'application' => $this->applicationPayload($candidate),
        ], 201);
    }

    public function applications(Request $request)
    {
        $applications = $request->user()->applications()
            ->with(['jobPosting:id,title,location', 'statusEvents', 'shortlistingResponse'])
            ->orderByDesc('created_at')->get();

        return response()->json([
            'applications' => $applications->map(fn ($c) => $this->applicationPayload($c))->values(),
        ]);
    }

    public function application(Request $request, Candidate $candidate)
    {
        // Their own only. The tracking code route is the way in for anybody
        // else, and it needs the code.
        abort_unless($candidate->job_seeker_id === $request->user()->id, 404);

        $candidate->load(['jobPosting:id,title,location', 'statusEvents', 'shortlistingResponse', 'documents']);

        return response()->json(['application' => $this->applicationPayload($candidate)]);
    }

    // ----------------------------------------------------------------
    // notifications
    // ----------------------------------------------------------------

    public function notifications(Request $request)
    {
        $seeker = $request->user();

        return response()->json([
            'unread'        => JobSeekerNotification::where('job_seeker_id', $seeker->id)->unread()->count(),
            'notifications' => JobSeekerNotification::where('job_seeker_id', $seeker->id)
                ->orderByDesc('created_at')->limit(50)->get()
                ->map(fn ($n) => [
                    'id'         => $n->id,
                    'type'       => $n->type,
                    'title'      => $n->title,
                    'body'       => $n->body,
                    'data'       => $n->data,
                    'action_url' => $n->action_url,
                    'read'       => $n->read_at !== null,
                    'created_at' => $n->created_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    public function markRead(Request $request)
    {
        $seeker = $request->user();
        $query  = JobSeekerNotification::where('job_seeker_id', $seeker->id)->unread();

        if ($request->filled('id')) $query->where('id', $request->integer('id'));

        $marked = $query->update(['read_at' => now()]);

        return response()->json(['marked' => $marked]);
    }

    // ----------------------------------------------------------------
    // shapes
    // ----------------------------------------------------------------

    private function jobPayload(JobPosting $job, bool $applied = false): array
    {
        return [
            'id'              => $job->id,
            'title'           => $job->title,
            'department'      => $job->department?->name,
            'category'        => $job->category?->name,
            'category_id'     => $job->job_category_id,
            'location'        => $job->location,
            'employment_type' => $job->employment_type,
            'type_label'      => ucwords(str_replace('_', ' ', $job->employment_type)),
            'vacancies'       => $job->vacancies,
            'applicants'      => $job->candidates_count ?? 0,
            'deadline'        => $job->deadline?->toDateString(),
            'closes_in_days'  => $job->deadline ? now()->startOfDay()->diffInDays($job->deadline, false) : null,
            'posted_at'       => $job->created_at?->toIso8601String(),
            'already_applied' => $applied,
        ];
    }

    private function seekerPayload(JobSeeker $seeker): array
    {
        return [
            'id'               => $seeker->id,
            'name'             => $seeker->name,
            'email'            => $seeker->email,
            'phone'            => $seeker->phone,
            'location'         => $seeker->location,
            'education_level'  => $seeker->education_level,
            'experience_years' => $seeker->experience_years,
            'notify_email'     => $seeker->notify_email,
            'notify_sms'       => $seeker->notify_sms,
            'categories'       => $seeker->categories()->get(['job_categories.id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'applications'     => $seeker->applications()->count(),
        ];
    }

    /**
     * One application, as the applicant is allowed to see it.
     *
     * No recruiter notes, no other applicants, no internal score breakdown -
     * only their own screening percentage and the stages they have reached.
     */
    private function applicationPayload(Candidate $candidate, bool $public = false): array
    {
        $pipeline = app(RecruitmentPipeline::class);
        $response = $candidate->shortlistingResponse;

        return [
            'id'            => $public ? null : $candidate->id,
            'tracking_code' => $candidate->tracking_code,
            'job'           => [
                'id'       => $candidate->job_posting_id,
                'title'    => $candidate->jobPosting?->title,
                'location' => $candidate->jobPosting?->location,
            ],
            'applied_at'    => $candidate->created_at?->toIso8601String(),
            'stage'         => $pipeline->stageOf($candidate->status),
            'stage_label'   => collect($pipeline->progressFor($candidate))
                ->firstWhere('state', 'current')['label'] ?? 'Outcome',
            'progress'      => $pipeline->progressFor($candidate),
            'assessment'    => $response && $response->max_score > 0 ? [
                'percentage' => (float) $response->percentage,
                'score'      => (float) $response->total_score,
                'max'        => (float) $response->max_score,
            ] : null,
            'documents'     => $candidate->relationLoaded('documents')
                ? $candidate->documents->map(fn ($d) => [
                    'type' => $d->type, 'label' => $d->label, 'size' => $d->size_label,
                ])->values() : [],
            'updates'       => $candidate->statusEvents->sortByDesc('created_at')->values()
                ->map(fn ($e) => [
                    'at'      => $e->created_at?->toIso8601String(),
                    'status'  => $e->to_status,
                    'message' => $e->message,
                ])->values(),
        ];
    }
}
