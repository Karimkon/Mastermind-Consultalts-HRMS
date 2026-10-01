<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Recruitment\ShortlistingController;
use App\Jobs\ResolveApplicantOrigin;
use App\Models\{Candidate, CandidateDocument, Department, JobCategory, JobPosting, ShortlistingCriteria};
use App\Services\NotificationService;
use App\Services\QueueRunner;
use App\Services\RecruitmentPipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareersController extends Controller
{
    public function index(Request $request)
    {
        $jobs = JobPosting::with(["department", "category"])
            ->where("status", "open")
            ->where("is_public", true)
            ->withCount("candidates")
            ->when($request->department, fn($q) => $q->where("department_id", $request->department))
            ->when($request->category, fn($q) => $q->where("job_category_id", $request->category))
            ->when($request->type, fn($q) => $q->where("employment_type", $request->type))
            ->when($request->search, fn($q) => $q->where(fn($w) => $w
                ->where("title", "like", "%{$request->search}%")
                ->orWhere("location", "like", "%{$request->search}%")))
            ->orderByDesc("created_at")
            ->paginate(12)
            ->withQueryString();

        $departments = Department::whereHas("jobPostings", fn($q) => $q->where("status","open")->where("is_public",true))->get();
        $categories  = JobCategory::active()->orderBy('sort_order')->get();
        $totalJobs   = JobPosting::where("status","open")->where("is_public",true)->count();

        // What everybody else is applying for. Asked for by the brief, and it
        // tells an applicant something useful: where the competition is.
        $topCategories = $this->topCategories();

        return view("careers.index", compact(
            "jobs", "departments", "categories", "totalJobs", "topCategories"
        ));
    }

    public function show(JobPosting $job)
    {
        abort_unless($job->status === "open" && $job->is_public, 404);
        $job->load(["department", "category"])->loadCount("candidates");

        $relatedJobs = JobPosting::with("department")
            ->where("status","open")->where("is_public",true)
            ->where("id","!=",$job->id)
            ->where("department_id", $job->department_id)
            ->limit(3)->get();

        $screeningCriteria = ShortlistingCriteria::with('questions')
            ->where('job_posting_id', $job->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        $documentTypes = CandidateDocument::TYPES;
        $topCategories = $this->topCategories();

        return view("careers.show", compact(
            "job", "relatedJobs", "screeningCriteria", "documentTypes", "topCategories"
        ));
    }

    public function apply(Request $request, JobPosting $job)
    {
        abort_unless($job->status === "open" && $job->is_public, 404);

        $request->validate(
            array_merge([
                "name"         => "required|string|max:255",
                "email"        => "required|email|max:255",
                "phone"        => "nullable|string|max:20",
                "cover_letter" => "nullable|string|max:2000",
                "cv"           => "required|file|max:8192|mimes:pdf,doc,docx",
            ], $this->documentRules()),
            $this->documentMessages()
        );

        self::requireScreeningAnswers($request, $this->activeCriteria($job));

        // One application per person per position. Without this, pressing
        // Submit twice on a slow connection creates two candidates and the
        // applicant gets two of every message that follows.
        $existing = Candidate::where('job_posting_id', $job->id)
            ->where('email', $request->email)->first();

        if ($existing) {
            return redirect()->route('careers.status.show', $existing->tracking_code)
                ->with('info', 'You have already applied for this position. Here is where your application stands.');
        }

        $candidate = DB::transaction(function () use ($request, $job) {
            $nameParts = preg_split('/\s+/', trim($request->name), 2);

            $candidate = Candidate::create([
                "job_posting_id" => $job->id,
                "first_name"     => $nameParts[0],
                "last_name"      => $nameParts[1] ?? "",
                "email"          => $request->email,
                "phone"          => $request->phone,
                "cover_letter"   => $request->cover_letter,
                "status"         => "new",
                "source"         => "careers_page",

                // Kept now, resolved to a place later. This is the only
                // moment the address is available.
                "ip_address"     => $request->ip(),
            ]);

            $cv = $this->storeDocument($candidate, 'cv', $request->file('cv'));
            $candidate->forceFill(['resume_path' => $cv->path])->save();

            $this->storeExtraDocuments($request, $candidate);

            return $candidate;
        });

        $this->saveScreening($request, $job, $candidate);

        app(NotificationService::class)->newApplication($candidate);
        app(RecruitmentPipeline::class)->recordApplied($candidate);

        ResolveApplicantOrigin::dispatch($candidate->id);
        QueueRunner::kick();

        // The thank-you page reads the score off the saved response, so there
        // is nothing to carry in the session.
        return redirect()->route("careers.applied", $candidate->tracking_code);
    }

    /**
     * The thank-you page, which also shows the applicant their own screening
     * score - the brief asked for this by name, and until now the score was
     * computed and shown only to recruiters.
     */
    public function applied(string $code)
    {
        $candidate = Candidate::with(['jobPosting', 'shortlistingResponse', 'documents'])
            ->where('tracking_code', $code)->firstOrFail();

        $progress = app(RecruitmentPipeline::class)->progressFor($candidate->load('statusEvents'));

        return view('careers.applied', [
            'candidate' => $candidate,
            'progress'  => $progress,
            'response'  => $candidate->shortlistingResponse,
        ]);
    }

    /** The form where somebody types the reference they were given. */
    public function statusLookup()
    {
        return view('careers.status-lookup');
    }

    public function statusFind(Request $request)
    {
        $request->validate(['tracking_code' => 'required|string|max:16']);

        $candidate = Candidate::where('tracking_code', strtoupper(trim($request->tracking_code)))->first();

        if (! $candidate) {
            return back()->withInput()
                ->withErrors(['tracking_code' => 'No application found with that reference. Check the characters and try again.']);
        }

        return redirect()->route('careers.status.show', $candidate->tracking_code);
    }

    /**
     * Where one application stands.
     *
     * The tracking code is the whole of the authentication, so this shows
     * only what the applicant already knows about themselves - never another
     * applicant's details, and never the recruiter's internal notes.
     */
    public function statusShow(string $code)
    {
        $candidate = Candidate::with([
            'jobPosting.category', 'shortlistingResponse', 'documents', 'statusEvents',
        ])->where('tracking_code', $code)->firstOrFail();

        $pipeline = app(RecruitmentPipeline::class);

        return view('careers.status', [
            'candidate'      => $candidate,
            'progress'       => $pipeline->progressFor($candidate),
            'response'       => $candidate->shortlistingResponse,
            'applicantCount' => Candidate::where('job_posting_id', $candidate->job_posting_id)->count(),
            'topCategories'  => $this->topCategories(),
        ]);
    }

    // ----------------------------------------------------------------
    // helpers
    // ----------------------------------------------------------------

    /**
     * The three categories most applied for, as a share of all applications.
     *
     * Applications with no category (a posting nobody classified) are counted
     * in the total but cannot top the list, which is the honest treatment:
     * leaving them out would inflate everybody else's percentage.
     */
    public function topCategories(int $take = 3): array
    {
        $total = Candidate::count();
        if ($total === 0) return [];

        $rows = Candidate::query()
            ->join('job_postings', 'job_postings.id', '=', 'candidates.job_posting_id')
            ->leftJoin('job_categories', 'job_categories.id', '=', 'job_postings.job_category_id')
            ->selectRaw('COALESCE(job_categories.name, ?) as label, COUNT(*) as total', ['Unclassified'])
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit($take)
            ->get();

        return $rows->map(fn ($r) => [
            'label'   => $r->label,
            'count'   => (int) $r->total,
            'percent' => round($r->total * 100 / $total, 1),
        ])->all();
    }

    /** Validation for the optional papers, built from one list of types. */
    private function documentRules(): array
    {
        $rules = [];

        foreach (CandidateDocument::TYPES as $type => $meta) {
            if ($type === 'cv') continue;           // handled on its own, required

            $allowed = $type === 'passport_photo' ? 'jpg,jpeg,png' : 'pdf,doc,docx,jpg,jpeg,png';

            if ($meta['multiple']) {
                $rules["documents.{$type}"]   = 'nullable|array|max:6';
                $rules["documents.{$type}.*"] = "file|max:8192|mimes:{$allowed}";
            } else {
                $rules["documents.{$type}"] = "nullable|file|max:8192|mimes:{$allowed}";
            }
        }

        return $rules;
    }

    private function documentMessages(): array
    {
        $messages = [
            'cv.required' => 'Please attach your CV.',
            'cv.mimes'    => 'Your CV must be a PDF or Word document.',
            'cv.max'      => 'Your CV is larger than 8MB.',
        ];

        foreach (CandidateDocument::TYPES as $type => $meta) {
            $label = $meta['label'];
            $key   = $meta['multiple'] ? "documents.{$type}.*" : "documents.{$type}";
            $messages["{$key}.mimes"] = "{$label}: PDF, Word or an image only.";
            $messages["{$key}.max"]   = "{$label}: each file must be under 8MB.";
        }

        return $messages;
    }

    /**
     * Store one file against an application.
     *
     * The local disk, not public: these are somebody's academic papers and
     * police letter. They come back out only through a controller that checks
     * who is asking.
     */
    public function storeDocument(Candidate $candidate, string $type, $file): CandidateDocument
    {
        $path = $file->store("applications/{$candidate->job_posting_id}/{$candidate->id}", 'local');

        return CandidateDocument::create([
            'candidate_id'  => $candidate->id,
            'type'          => $type,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes'    => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
        ]);
    }

    private function storeExtraDocuments(Request $request, Candidate $candidate): void
    {
        foreach (CandidateDocument::TYPES as $type => $meta) {
            if ($type === 'cv') continue;

            $input = $request->file("documents.{$type}");
            if (! $input) continue;

            foreach (is_array($input) ? $input : [$input] as $file) {
                $this->storeDocument($candidate, $type, $file);
            }
        }
    }

    /** The questionnaire a job is currently screening with, if any. */
    public static function activeCriteria(JobPosting $job): ?ShortlistingCriteria
    {
        return ShortlistingCriteria::with('questions')
            ->where('job_posting_id', $job->id)
            ->where('is_active', true)
            ->latest()
            ->first();
    }
    /**
     * Fail the application if the questionnaire was not completed.
     *
     * Shared by the careers page and the app, so neither can be the lenient
     * one. Written answers count: they are read by a person, and a blank is
     * no more acceptable there than on a multiple choice.
     */
    public static function requireScreeningAnswers(Request $request, ?ShortlistingCriteria $criteria): void
    {
        if (! $criteria || $criteria->questions->isEmpty()) return;

        $missing = [];
        foreach ($criteria->questions as $question) {
            $answer = $request->input("screening.{$question->id}");
            if ($answer === null || $answer === '' || (is_array($answer) && ! $answer)) {
                $missing["screening.{$question->id}"] = 'Please answer this question.';
            }
        }

        if ($missing) {
            throw ValidationException::withMessages(array_merge($missing, [
                'screening' => count($missing) === 1
                    ? 'One screening question has not been answered.'
                    : count($missing) . ' screening questions have not been answered.',
            ]));
        }
    }

    /**
     * Save the screening answers and return the percentage scored, or null
     * when this posting has no questionnaire.
     *
     * The applicant sees the figure on the thank-you page, read back off the
     * saved response rather than carried through the session.
     */
    private function saveScreening(Request $request, JobPosting $job, Candidate $candidate): ?float
    {
        $criteria = $this->activeCriteria($job);

        if (! $criteria || $criteria->questions->isEmpty()) return null;

        $answers = [];
        foreach ($criteria->questions as $question) {
            $answers[$question->id] = $request->input("screening.{$question->id}");
        }

        ShortlistingController::saveResponses($candidate, $criteria, $answers);

        return (float) $candidate->shortlistingResponse()->first()?->percentage;
    }
}
