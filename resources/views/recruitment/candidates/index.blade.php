@extends('layouts.app')
@section('title', 'Candidates')
@section('content')
<x-page-header title="Candidates" subtitle="Track all job applicants">
    <a href="{{ route('recruitment.candidates.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> Add Candidate</a>
</x-page-header>

<x-filter-bar :action="route('recruitment.candidates.index')">
    <div class="flex-1 min-w-[180px]"><input type="text" name="search" value="{{ request('search') }}" placeholder="Search name/email..." class="form-input w-full"></div>
    <div class="w-52"><select name="job_posting_id" class="form-input w-full select2"><option value="">All Jobs</option>@foreach($jobs as $j)<option value="{{ $j->id }}" @selected(request('job_posting_id')==$j->id)>{{ $j->title }}</option>@endforeach</select></div>
    <div class="w-40"><select name="status" class="form-input w-full"><option value="">All Status</option>@foreach(['applied','screening','interview','offered','hired','rejected'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
</x-filter-bar>

<x-data-table>
    <thead><tr class="table-header"><th>Candidate</th><th>Job</th><th>AI Score</th><th>Status</th><th>Phone</th><th>Applied</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($candidates as $c)
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ $c->name }}</div>
            <div class="text-xs text-slate-500">{{ $c->email }}</div>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $c->jobPosting->title ?? '—' }}</td>
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="h-1.5 w-16 bg-slate-100 rounded-full"><div class="h-1.5 rounded-full {{ $c->score >= 70 ? 'bg-green-500' : ($c->score >= 40 ? 'bg-yellow-500' : 'bg-red-400') }}" style="width:{{ $c->score }}%"></div></div>
                <span class="text-xs font-medium text-slate-700">{{ $c->score }}%</span>
            </div>
        </td>
        <td class="px-4 py-3">
            @php $sc2 = ['applied'=>'badge-blue','screening'=>'badge-yellow','interview'=>'badge-purple','offered'=>'badge-indigo','hired'=>'badge-green','rejected'=>'badge-red']; @endphp
            <span class="badge {{ $sc2[$c->status] ?? 'badge-slate' }}">{{ ucfirst($c->status) }}</span>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $c->phone ?? '—' }}</td>
        <td class="px-4 py-3 text-xs text-slate-500">{{ $c->created_at->format('M d, Y') }}</td>
        <td class="px-4 py-3 flex gap-2">
            <a href="{{ route('recruitment.candidates.show', $c) }}" class="btn-xs btn-blue">View</a>
        </td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-center py-12 text-slate-400"><i class="fas fa-users text-3xl mb-2 block opacity-30"></i>No candidates found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $candidates->withQueryString()->links() }}</div>
@endsection