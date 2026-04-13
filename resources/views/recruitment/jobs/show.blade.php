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
        <div class="card p-5 space-y-3">
            <h3 class="font-semibold text-slate-700">Job Details</h3>
            <div class="text-sm space-y-2">
                <div class="flex justify-between"><span class="text-slate-500">Type</span><span class="font-medium">{{ ucfirst($job->type) }}</span></div>
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