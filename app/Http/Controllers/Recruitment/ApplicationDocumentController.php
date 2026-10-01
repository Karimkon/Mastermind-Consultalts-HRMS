<?php
namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Hands an applicant's papers to somebody entitled to read them.
 *
 * These files are a stranger's CV, academic certificates, passport photo,
 * LC letter and police letter. They are stored off the web root on the local
 * disk, so there is no URL that serves them without passing through here -
 * and here checks who is asking on every single fetch.
 *
 * The views used to link to Storage::url(), which points at the public disk.
 * That produced a 404 because the files are not there, which was the lucky
 * outcome: had they been there, the link would have handed anyone who came by
 * the URL somebody else's police letter without so much as a login.
 */
class ApplicationDocumentController extends Controller
{
    /** A document that is a row in candidate_documents. */
    public function show(Request $request, CandidateDocument $document)
    {
        $document->loadMissing('candidate');
        abort_unless($document->candidate, 404);

        $this->authoriseFor($request, $document->candidate);

        return $this->serve(
            $document->path,
            $document->original_name ?: basename($document->path),
            $document->mime_type,
        );
    }

    /**
     * The CV, reached through the candidate.
     *
     * `candidates.resume_path` predates the documents table and is still the
     * column two screens read, so it keeps its own way in rather than those
     * screens silently losing their link.
     */
    public function resume(Request $request, Candidate $candidate)
    {
        abort_unless(filled($candidate->resume_path), 404, 'No CV was attached to this application.');

        $this->authoriseFor($request, $candidate);

        $name = trim($candidate->name) !== ''
            ? $candidate->name . ' - CV.' . pathinfo($candidate->resume_path, PATHINFO_EXTENSION)
            : basename($candidate->resume_path);

        return $this->serve($candidate->resume_path, $name);
    }

    // ----------------------------------------------------------------

    /**
     * Who may read this application's papers.
     *
     * Recruitment staff may read any. A client may read only the candidates
     * on their own postings - the same rule ClientRecruitmentController
     * applies to the pages, applied here so the files cannot be reached
     * around the side of those pages.
     */
    private function authoriseFor(Request $request, Candidate $candidate): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->hasAnyRole(['super-admin', 'hr-admin', 'manager', 'recruiter', 'md'])) {
            return;
        }

        if ($user->hasRole('client')) {
            $client = Client::where('user_id', $user->id)->first();
            abort_unless($client, 403);

            $theirs = $client->jobPostings()->pluck('job_postings.id');
            abort_unless($theirs->contains($candidate->job_posting_id), 403);

            return;
        }

        abort(403);
    }

    private function serve(string $path, string $downloadName, ?string $mime = null)
    {
        abort_unless(Storage::disk('local')->exists($path), 404, 'That file is no longer on the server.');

        $mime = $mime ?: (Storage::disk('local')->mimeType($path) ?: 'application/octet-stream');

        // A PDF or an image opens in the browser, which is what a recruiter
        // skimming twenty applications wants. Anything else downloads.
        $inline = str_starts_with($mime, 'image/') || $mime === 'application/pdf';

        return response()->file(
            Storage::disk('local')->path($path),
            [
                'Content-Type'        => $mime,
                'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                    . '; filename="' . addslashes($downloadName) . '"',
            ]
        );
    }
}
