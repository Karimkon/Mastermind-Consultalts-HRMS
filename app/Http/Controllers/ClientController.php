<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function dashboard()
    {
        $client = auth()->user()->employee?->client
            ?? \App\Models\Client::where('user_id', auth()->id())->firstOrFail();

        $pendingLeaves = \App\Models\LeaveRequest::whereIn(
                'employee_id', $client->employees()->pluck('employees.id')
            )
            ->where('client_approval_required', true)
            ->where('client_approval_status', 'pending')
            ->count();

        $pendingShortlist = \App\Models\Candidate::whereIn(
                'job_posting_id', $client->jobPostings()->pluck('job_postings.id')
            )
            ->where('status', 'shortlisted')
            ->whereNull('client_shortlist_status')
            ->count();

        $approvedLeaves = \App\Models\LeaveRequest::whereIn(
                'employee_id', $client->employees()->pluck('employees.id')
            )
            ->where('client_approval_status', 'approved')
            ->count();

        $hiredCandidates = \App\Models\Candidate::whereIn(
                'job_posting_id', $client->jobPostings()->pluck('job_postings.id')
            )
            ->where('status', 'hired')
            ->count();

        return view('client.dashboard', compact(
            'client', 'pendingLeaves', 'pendingShortlist', 'approvedLeaves', 'hiredCandidates'
        ));
    }
}
