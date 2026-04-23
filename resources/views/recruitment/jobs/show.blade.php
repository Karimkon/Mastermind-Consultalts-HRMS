@extends('layouts.app')
@section('title', $job->title)
@section('content')
<x-page-header :title="$job->title" :subtitle="$job->department->name ?? 'No Department'">
    <a href="{{ route('recruitment.candidates.create', ['job_posting_id' => $job->id]) }}" class="btn-primary"><i class="fas fa-user-plus mr-1"></i> Add Candidate</a>
    <a href="{{ route('recruitment.jobs.edit', $job) }}" class="btn-secondary"><i class="fas fa-edit mr-1"></i> Edit</a>
</x-page-header>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-3">Job Description</h3>
            <div class="prose prose-sm text-slate-600">{{ $job->description }}</div>
        </div>
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-3">Requirements</h3>
            <div class="prose prose-sm text-slate-600">{{ $job->requirements }}</div>
        </div>
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-700">Candidates ({{ $candidates->total() }})</h3>
            </div>
            <x-data-table>
                <thead><tr class="table-header"><th>Name</th><th>Email</th><th>AI Score</th><th>Status</th><th>Applied</th><th></th></tr></thead>
                <tbody>
                @forelse($candidates as $c)
                <tr class="table-row">
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $c->name }}</td>
                    <td class="px-4 py-3 text-slate-600 text-sm">{{ $c->email }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-20 bg-slate-100 rounded-full"><div class="h-1.5 bg-blue-500 rounded-full" style="width:{{ $c->score }}%"></div></div>
                            <span class="text-xs text-slate-600">{{ $c->score }}%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="badge badge-blue">{{ ucfirst($c->status) }}</span></td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $c->created_at->format('M d, Y') }}</td>
                    <td class="px-4 py-3"><a href="{{ route('recruitment.candidates.show', $c) }}" class="text-blue-600 hover:underline text-xs">View</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-slate-400">No candidates yet.</td></tr>
                @endforelse
                </tbody>
            </x-data-table>
            <div class="mt-3">{{ $candidates->links() }}</div>
        </div>
    </div>
    <div class="space-y-4">
        {{-- AI Candidate Ranking --}}
        <div class="card p-5" x-data="{ loading: false, rankings: null, error: null }">
            <h3 class="font-semibold text-slate-700 mb-3"><i class="fas fa-robot text-blue-500 mr-2"></i>AI Ranking</h3>
            <p class="text-xs text-slate-500 mb-3">Let AI rank and recommend all candidates for this position based on their profiles and scores.</p>
            <button @click="loading=true; error=null; rankings=null; fetch('{{ route('recruitment.ai.shortlist', $job) }}',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('[name=csrf-token]').content,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{loading=false; if(d.error)error=d.error; else rankings=d.rankings;}).catch(()=>{loading=false;error='Request failed';})"
                :disabled="loading"
                class="btn-primary w-full justify-center text-sm">
                <i class="fas fa-sort-amount-down mr-2" :class="loading ? 'fa-spin' : ''"></i>
                <span x-text="loading ? 'Ranking candidates...' : 'AI Rank All Candidates'"></span>
            </button>
            <p x-show="error" x-text="error" class="text-xs text-red-500 mt-2"></p>
            <div x-show="rankings && rankings.length" class="mt-3 space-y-2">
                <template x-for="r in (rankings||[])" :key="r.candidate_id">
                    <div class="p-2 bg-slate-50 rounded-lg text-xs border border-slate-100">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-semibold text-slate-700">#<span x-text="r.rank"></span></span>
                            <span class="badge-xs" :class="{'badge-green': r.recommendation==='Strongly Recommend'||r.recommendation==='Recommend', 'badge-yellow': r.recommendation==='Consider', 'badge-red': r.recommendation==='Do Not Recommend'}" x-text="r.recommendation"></span>
                        </div>
                        <p class="text-slate-500" x-text="r.reasoning"></p>
                    </div>
                </template>
            </div>
        </div>

        <div class="card p-5 space-y-3">
            <h3 class="font-semibold text-slate-700">Job Details</h3>
            <div class="text-sm space-y-2">
                <div class="flex justify-between"><span class="text-slate-500">Type</span><span class="font-medium">{{ ucwords(str_replace('_', ' ', $job->employment_type)) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Location</span><span class="font-medium">{{ $job->location ?? 'N/A' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Vacancies</span><span class="font-medium">{{ $job->vacancies ?? 1 }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Deadline</span><span class="font-medium">{{ $job->deadline ? \Carbon\Carbon::parse($job->deadline)->format('M d, Y') : 'N/A' }}</span></div>
                @if($job->salary_min || $job->salary_max)
                <div class="flex justify-between"><span class="text-slate-500">Salary</span><span class="font-medium">{{ number_format($job->salary_min) }} – {{ number_format($job->salary_max) }}</span></div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection