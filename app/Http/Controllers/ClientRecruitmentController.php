<?php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Candidate;
use Illuminate\Http\Request;

class ClientRecruitmentController extends Controller
{
    private function getClient(): Client
    {
        return Client::where('user_id', auth()->id())->firstOrFail();
    }

    public function index(Request $request)
    {
        $client = $this->getClient();
        $jobIds = $client->jobPostings()->pluck('job_postings.id');

        $candidates = Candidate::with(['jobPosting'])
            ->whereIn('job_posting_id', $jobIds)
            ->where('status', 'shortlisted')
            ->when($request->status, fn($q) => $q->where('client_shortlist_status', $request->status))
            ->when($request->job_id, fn($q) => $q->where('job_posting_id', $request->job_id))
            ->orderByRaw("FIELD(client_shortlist_status, NULL, 'pending', 'approved', 'rejected') ASC")
            ->orderBy('score', 'desc')
            ->paginate(20);

        $jobPostings = $client->jobPostings()->get();

        return view('client.recruitment.index', compact('client', 'candidates', 'jobPostings'));
    }

    public function show(Candidate $candidate)
    {
        $client = $this->getClient();
        $jobIds = $client->jobPostings()->pluck('job_postings.id');

        abort_unless($jobIds->contains($candidate->job_posting_id), 403);

        $candidate->load(['jobPosting', 'interviews.interviewer']);

        return view('client.recruitment.show', compact('client', 'candidate'));
    }

    public function approve(Request $request, Candidate $candidate)
    {
        $client = $this->getClient();
        $jobIds = $client->jobPostings()->pluck('job_postings.id');

        abort_unless($jobIds->contains($candidate->job_posting_id), 403);

        $candidate->update([
            'client_shortlist_status' => 'approved',
            'client_shortlisted_by'   => $client->id,
            'client_shortlist_notes'  => $request->notes,
            'client_actioned_at'      => now(),
            'status'                  => 'interview',
        ]);

        return redirect()->route('client.recruitment.index')
            ->with('success', "{$candidate->name} approved for interview.");
    }

    public function reject(Request $request, Candidate $candidate)
    {
        $client = $this->getClient();
        $jobIds = $client->jobPostings()->pluck('job_postings.id');

        abort_unless($jobIds->contains($candidate->job_posting_id), 403);

        $candidate->update([
            'client_shortlist_status' => 'rejected',
            'client_shortlisted_by'   => $client->id,
            'client_shortlist_notes'  => $request->notes,
            'client_actioned_at'      => now(),
            'status'                  => 'rejected',
        ]);

        return redirect()->route('client.recruitment.index')
            ->with('success', "{$candidate->name} rejected.");
    }
}
