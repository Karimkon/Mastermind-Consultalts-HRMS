@extends('layouts.app')
@section('title', 'Client Dashboard')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Welcome, {{ $client->company_name }}</h1>
    <p class="text-slate-500 text-sm mt-1">Client Portal — {{ now()->format('l, d F Y') }}</p>
</div>

{{-- Stat Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center shrink-0">
            <i class="fas fa-clock text-yellow-600 text-xl"></i>
        </div>
        <div>
            <p class="text-sm text-slate-500">Pending Leave Approvals</p>
            <p class="text-2xl font-bold text-slate-800">{{ $pendingLeaves }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
            <i class="fas fa-user-check text-blue-600 text-xl"></i>
        </div>
        <div>
            <p class="text-sm text-slate-500">Candidates to Review</p>
            <p class="text-2xl font-bold text-slate-800">{{ $pendingShortlist }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
            <i class="fas fa-calendar-check text-green-600 text-xl"></i>
        </div>
        <div>
            <p class="text-sm text-slate-500">Leaves Approved</p>
            <p class="text-2xl font-bold text-slate-800">{{ $approvedLeaves }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
            <i class="fas fa-user-tie text-purple-600 text-xl"></i>
        </div>
        <div>
            <p class="text-sm text-slate-500">Staff Hired</p>
            <p class="text-2xl font-bold text-slate-800">{{ $hiredCandidates }}</p>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-slate-700">Leave Approvals</h2>
            <a href="{{ route('client.leaves.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        @if($pendingLeaves > 0)
            <div class="flex items-center gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                <i class="fas fa-exclamation-circle text-yellow-500 text-lg"></i>
                <div>
                    <p class="text-sm font-medium text-yellow-800">{{ $pendingLeaves }} leave request{{ $pendingLeaves > 1 ? 's' : '' }} awaiting your approval</p>
                    <a href="{{ route('client.leaves.index') }}" class="text-xs text-yellow-700 hover:underline mt-0.5 block">Review now →</a>
                </div>
            </div>
        @else
            <p class="text-sm text-slate-400 py-4 text-center"><i class="fas fa-check-circle text-green-400 mr-1"></i>No pending leave approvals</p>
        @endif
    </div>

    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-slate-700">Candidate Shortlisting</h2>
            <a href="{{ route('client.recruitment.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        @if($pendingShortlist > 0)
            <div class="flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                <i class="fas fa-user-clock text-blue-500 text-lg"></i>
                <div>
                    <p class="text-sm font-medium text-blue-800">{{ $pendingShortlist }} candidate{{ $pendingShortlist > 1 ? 's' : '' }} shortlisted for your review</p>
                    <a href="{{ route('client.recruitment.index') }}" class="text-xs text-blue-700 hover:underline mt-0.5 block">Review now →</a>
                </div>
            </div>
        @else
            <p class="text-sm text-slate-400 py-4 text-center"><i class="fas fa-check-circle text-green-400 mr-1"></i>No candidates pending review</p>
        @endif
    </div>
</div>
@endsection
