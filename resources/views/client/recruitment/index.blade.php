@extends('layouts.app')
@section('title', 'Candidate Shortlisting')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Candidate Shortlisting</h1>
        <p class="text-slate-500 text-sm mt-1">Review shortlisted candidates for your open positions</p>
    </div>
</div>

{{-- Filter --}}
<form method="GET" class="card p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="job_id" class="form-select w-56">
        <option value="">All Positions</option>
        @foreach($jobPostings as $job)
        <option value="{{ $job->id }}" {{ request('job_id') == $job->id ? 'selected' : '' }}>{{ $job->title }}</option>
        @endforeach
    </select>
    <select name="status" class="form-select w-44">
        <option value="">All Decisions</option>
        <option value="" {{ !request('status') ? 'selected' : '' }}>Pending Review</option>
        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
    </select>
    <button type="submit" class="btn-primary">Filter</button>
    @if(request()->hasAny(['job_id','status']))<a href="{{ route('client.recruitment.index') }}" class="btn-secondary">Clear</a>@endif
</form>

<div class="card overflow-hidden">
    <table class="w-full">
        <thead class="table-header">
            <tr>
                <th>Candidate</th>
                <th>Position</th>
                <th>AI Score</th>
                <th>Status</th>
                <th>Your Decision</th>
                <th>Applied</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($candidates as $candidate)
            <tr class="table-row">
                <td class="px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $candidate->name }}</p>
                        <p class="text-xs text-slate-400">{{ $candidate->email }}</p>
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ $candidate->jobPosting->title }}</td>
                <td class="px-4 py-3">
                    @if($candidate->score !== null)
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-slate-100 rounded-full h-1.5 w-20">
                            <div class="h-1.5 rounded-full {{ $candidate->score >= 70 ? 'bg-green-500' : ($candidate->score >= 40 ? 'bg-yellow-500' : 'bg-red-400') }}"
                                 style="width:{{ $candidate->score }}%"></div>
                        </div>
                        <span class="text-xs font-semibold {{ $candidate->score >= 70 ? 'text-green-600' : ($candidate->score >= 40 ? 'text-yellow-600' : 'text-red-500') }}">
                            {{ $candidate->score }}%
                        </span>
                    </div>
                    @else
                    <span class="text-xs text-slate-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="badge-blue">Shortlisted</span>
                </td>
                <td class="px-4 py-3">
                    @if($candidate->client_shortlist_status === 'approved')
                        <span class="badge-green"><i class="fas fa-check mr-1"></i>Approved</span>
                    @elseif($candidate->client_shortlist_status === 'rejected')
                        <span class="badge-red"><i class="fas fa-times mr-1"></i>Rejected</span>
                    @else
                        <span class="badge-yellow">Pending</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-slate-400">{{ $candidate->created_at->format('d M Y') }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('client.recruitment.show', $candidate) }}" class="btn-xs btn-blue">View</a>
                        @if(!$candidate->client_shortlist_status)
                        <form method="POST" action="{{ route('client.recruitment.approve', $candidate) }}" onsubmit="return confirm('Approve {{ $candidate->name }} for interview?')">
                            @csrf
                            <button type="submit" class="btn-xs btn-green">Approve</button>
                        </form>
                        <button onclick="showRejectModal({{ $candidate->id }})" class="btn-xs" style="background:#fee2e2;color:#991b1b;">Reject</button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                    <i class="fas fa-user-tie text-4xl mb-3 block"></i>
                    No shortlisted candidates found
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $candidates->withQueryString()->links() }}</div>

{{-- Reject Modal --}}
<div id="reject-modal" class="fixed inset-0 bg-black/40 z-50 hidden items-center justify-center" onclick="closeRejectModal(event)">
    <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl" onclick="event.stopPropagation()">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Reject Candidate</h3>
        <form id="reject-form" method="POST">
            @csrf
            <div class="mb-4">
                <label class="form-label">Reason (optional)</label>
                <textarea name="notes" rows="3" class="form-textarea" placeholder="Briefly explain why..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-danger">Reject Candidate</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function showRejectModal(id) {
    document.getElementById('reject-form').action = `/client/recruitment/${id}/reject`;
    const m = document.getElementById('reject-modal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeRejectModal(e) {
    if (!e || e.target === document.getElementById('reject-modal')) {
        const m = document.getElementById('reject-modal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }
}
</script>
@endpush
@endsection
