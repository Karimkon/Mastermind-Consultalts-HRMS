<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{LeaveRequest, Candidate, JobPosting};
use Illuminate\Http\Request;

class ClientPortalApiController extends Controller
{
    private function client(Request $request)
    {
        return $request->user()->client;
    }

    public function dashboard(Request $request)
    {
        $client = $this->client($request);
        if (!$client) return response()->json(['message' => 'No client profile.'], 403);

        $employeeIds    = $client->employees()->pluck('employees.id');
        $jobPostingIds  = $client->jobPostings()->pluck('job_postings.id');

        return response()->json([
            'data' => [
                'company_name'         => $client->company_name,
                'assigned_employees'   => $employeeIds->count(),
                'assigned_jobs'        => $jobPostingIds->count(),
                'pending_leaves'       => LeaveRequest::whereIn('employee_id', $employeeIds)
                    ->where('client_approval_required', true)
                    ->where('client_approval_status', 'pending')
                    ->where('status', 'approved')
                    ->count(),
                'pending_shortlists'   => Candidate::whereIn('job_posting_id', $jobPostingIds)
                    ->where('status', 'shortlisted')
                    ->whereNull('client_shortlist_status')
                    ->count(),
                'approved_this_month'  => LeaveRequest::whereIn('employee_id', $employeeIds)
                    ->where('client_approval_status', 'approved')
                    ->whereMonth('client_actioned_at', now()->month)
                    ->count(),
            ],
        ]);
    }

    public function leaves(Request $request)
    {
        $client = $this->client($request);
        if (!$client) return response()->json(['message' => 'No client profile.'], 403);

        $employeeIds = $client->employees()->pluck('employees.id');

        $query = LeaveRequest::with(['employee', 'leaveType'])
            ->whereIn('employee_id', $employeeIds)
            ->where('client_approval_required', true);

        if ($request->status) {
            $query->where('client_approval_status', $request->status);
        }

        return response()->json([
            'data' => $query->latest()->paginate(20)->through(fn($l) => [
                'id'                     => $l->id,
                'employee'               => $l->employee?->full_name,
                'leave_type'             => $l->leaveType?->name,
                'start_date'             => $l->start_date?->format('Y-m-d'),
                'end_date'               => $l->end_date?->format('Y-m-d'),
                'days'                   => $l->total_days,
                'reason'                 => $l->reason,
                'hr_status'              => $l->status,
                'client_approval_status' => $l->client_approval_status ?? 'pending',
                'created_at'             => $l->created_at?->format('Y-m-d'),
            ]),
        ]);
    }

    public function approveLeave(Request $request, LeaveRequest $leave)
    {
        $client = $this->client($request);
        if (!$client) return response()->json(['message' => 'No client profile.'], 403);

        $employeeIds = $client->employees()->pluck('employees.id');
        if (!$employeeIds->contains($leave->employee_id)) abort(403);

        $leave->update([
            'client_approval_status' => 'approved',
            'client_approved_by'     => $request->user()->id,
            'client_actioned_at'     => now(),
        ]);

        return response()->json(['data' => ['client_approval_status' => 'approved']]);
    }

    public function rejectLeave(Request $request, LeaveRequest $leave)
    {
        $client = $this->client($request);
        if (!$client) return response()->json(['message' => 'No client profile.'], 403);

        $employeeIds = $client->employees()->pluck('employees.id');
        if (!$employeeIds->contains($leave->employee_id)) abort(403);

        $leave->update([
            'client_approval_status' => 'rejected',
            'client_approved_by'     => $request->user()->id,
            'client_actioned_at'     => now(),
        ]);

        return response()->json(['data' => ['client_approval_status' => 'rejected']]);
    }

    public function recruitment(Request $request)
    {
        $client = $this->client($request);
        if (!$client) return response()->json(['message' => 'No client profile.'], 403);

        $jobPostingIds = $client->jobPostings()->pluck('job_postings.id');

        $query = Candidate::with('jobPosting')
            ->whereIn('job_posting_id', $jobPostingIds)
            ->where('status', 'shortlisted');

        if ($request->status) {
            $query->where('client_shortlist_status', $request->status);
        }

        if ($request->job_id) {
            $query->where('job_posting_id', $request->job_id);
        }

        return response()->json([
            'data' => $query->orderBy('score', 'desc')->paginate(20)->through(fn($c) => [
                'id'                     => $c->id,
                'name'                   => $c->name,
                'email'                  => $c->email,
                'job_title'              => $c->jobPosting?->title,
                'job_posting_id'         => $c->job_posting_id,
                'score'                  => $c->score,
                'notes'                  => $c->notes,
                'client_shortlist_status'=> $c->client_shortlist_status,
                'client_shortlist_notes' => $c->client_shortlist_notes,
                'created_at'             => $c->created_at?->format('Y-m-d'),
            ]),
        ]);
    }

    public function approveCandidate(Request $request, Candidate $candidate)
    {
        $client = $this->client($request);
        if (!$client) return response()->json(['message' => 'No client profile.'], 403);

        $jobPostingIds = $client->jobPostings()->pluck('job_postings.id');
        if (!$jobPostingIds->contains($candidate->job_posting_id)) abort(403);

        $candidate->update([
            'client_shortlist_status' => 'approved',
            'client_shortlisted_by'   => $request->user()->id,
            'client_shortlist_notes'  => $request->notes,
            'client_actioned_at'      => now(),
            'status'                  => 'interview',
        ]);

        return response()->json(['data' => ['client_shortlist_status' => 'approved']]);
    }

    public function rejectCandidate(Request $request, Candidate $candidate)
    {
        $client = $this->client($request);
        if (!$client) return response()->json(['message' => 'No client profile.'], 403);

        $jobPostingIds = $client->jobPostings()->pluck('job_postings.id');
        if (!$jobPostingIds->contains($candidate->job_posting_id)) abort(403);

        $candidate->update([
            'client_shortlist_status' => 'rejected',
            'client_shortlisted_by'   => $request->user()->id,
            'client_shortlist_notes'  => $request->notes,
            'client_actioned_at'      => now(),
        ]);

        return response()->json(['data' => ['client_shortlist_status' => 'rejected']]);
    }

    public function assignedJobs(Request $request)
    {
        $client = $this->client($request);
        if (!$client) return response()->json(['message' => 'No client profile.'], 403);

        return response()->json([
            'data' => $client->jobPostings()->get()->map(fn($j) => [
                'id'    => $j->id,
                'title' => $j->title,
                'status'=> $j->status,
            ]),
        ]);
    }
}
