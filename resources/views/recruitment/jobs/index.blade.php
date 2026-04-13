@extends('layouts.app')
@section('title', 'Job Postings')
@section('content')
<x-page-header title="Job Postings" subtitle="Manage open positions and requisitions">
    <a href="{{ route('recruitment.jobs.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New Job</a>
</x-page-header>

<x-filter-bar :action="route('recruitment.jobs.index')">
    <div class="flex-1 min-w-[200px]">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title..." class="form-input w-full">
    </div>
    <div class="w-48">
        <select name="status" class="form-input w-full">
            <option value="">All Status</option>
            @foreach(['open','closed','draft','on-hold'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
</x-filter-bar>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($jobs as $job)
    <div class="card p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-3">
            <div>
                <h3 class="font-semibold text-slate-800 text-sm">{{ $job->title }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ $job->department->name ?? '—' }}</p>
            </div>
            @php
                $sc = ['open'=>'badge-green','closed'=>'badge-red','draft'=>'badge-slate','on-hold'=>'badge-yellow'];
            @endphp
            <span class="badge {{ $sc[$job->status] ?? 'badge-slate' }}">{{ ucfirst($job->status) }}</span>
        </div>
        <div class="flex items-center gap-4 text-xs text-slate-500 mb-4">
            <span><i class="fas fa-briefcase mr-1"></i>{{ ucfirst($job->type) }}</span>
            <span><i class="fas fa-map-marker-alt mr-1"></i>{{ $job->location ?? 'Remote' }}</span>
            <span><i class="fas fa-users mr-1"></i>{{ $job->candidates_count ?? 0 }} applicants</span>
        </div>
        <div class="flex items-center justify-between text-xs">
            <span class="text-slate-400">Deadline: {{ $job->deadline ? \Carbon\Carbon::parse($job->deadline)->format('M d, Y') : 'N/A' }}</span>
            <div class="flex gap-2">
                <a href="{{ route('recruitment.jobs.show', $job) }}" class="text-blue-600 hover:underline">View</a>
                <a href="{{ route('recruitment.jobs.edit', $job) }}" class="text-amber-600 hover:underline">Edit</a>
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-3 card p-12 text-center text-slate-400">
        <i class="fas fa-briefcase text-4xl mb-3 opacity-30"></i>
        <p>No job postings found.</p>
    </div>
    @endforelse
</div>
<div class="mt-4">{{ $jobs->withQueryString()->links() }}</div>
@endsection