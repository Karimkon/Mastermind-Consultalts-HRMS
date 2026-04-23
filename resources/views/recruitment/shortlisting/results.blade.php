@extends('layouts.app')
@section('title', 'Shortlisting Results — ' . $job->title)
@section('content')

<x-page-header :title="'Shortlisting Results'" :subtitle="$job->title">
    <a href="{{ route('recruitment.shortlisting.manage', $job) }}" class="btn-secondary"><i class="fas fa-cog mr-1"></i> Manage Criteria</a>
    <a href="{{ route('recruitment.jobs.show', $job) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to Job</a>
</x-page-header>

@if(session('success'))
<div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
    <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
    <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
</div>
@endif

{{-- Summary cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ $withResponses->count() }}</p>
        <p class="text-xs text-slate-500 mt-1">Completed Screening</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-slate-600">{{ $withoutResponses->count() }}</p>
        <p class="text-xs text-slate-500 mt-1">No Screening Submitted</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-emerald-600">{{ $criteria->top_n }}</p>
        <p class="text-xs text-slate-500 mt-1">Auto-Select Top N</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-purple-600">{{ $criteria->questions->count() }}</p>
        <p class="text-xs text-slate-500 mt-1">Screening Questions</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- Ranked list --}}
    <div class="xl:col-span-2 space-y-4">
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-700">Candidate Rankings</h3>
                <span class="text-xs text-slate-400">Sorted by screening score</span>
            </div>

            @forelse($withResponses as $response)
            @php
                $candidate  = $response->candidate;
                $isTopN     = $response->rank <= $topN;
                $percentage = $response->percentage;
                $barColor   = $percentage >= 70 ? 'bg-emerald-500' : ($percentage >= 40 ? 'bg-amber-400' : 'bg-red-400');
                $rankBg     = $response->rank === 1 ? 'bg-yellow-100 border-yellow-300'
                            : ($response->rank === 2 ? 'bg-slate-100 border-slate-300'
                            : ($response->rank === 3 ? 'bg-amber-50 border-amber-200'
                            : 'bg-white border-slate-200'));
            @endphp
            <div class="flex items-center gap-4 p-3 rounded-xl border {{ $rankBg }} mb-2 {{ $isTopN ? 'ring-1 ring-emerald-200' : '' }}">
                {{-- Rank badge --}}
                <div class="w-10 h-10 flex-shrink-0 rounded-full flex items-center justify-center font-bold text-sm
                    {{ $response->rank === 1 ? 'bg-yellow-400 text-yellow-900'
                     : ($response->rank === 2 ? 'bg-slate-400 text-white'
                     : ($response->rank === 3 ? 'bg-amber-500 text-white'
                     : 'bg-slate-200 text-slate-600')) }}">
                    #{{ $response->rank }}
                </div>

                {{-- Candidate info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('recruitment.candidates.show', $candidate) }}"
                            class="font-semibold text-slate-800 text-sm hover:text-blue-600">
                            {{ $candidate->name }}
                        </a>
                        @if($isTopN)
                        <span class="badge badge-green text-xs">Top {{ $topN }}</span>
                        @endif
                        <span class="badge
                            {{ $candidate->status === 'shortlisted' ? 'badge-green'
                             : ($candidate->status === 'rejected'    ? 'badge-red'
                             : 'badge-blue') }} text-xs">
                            {{ ucfirst($candidate->status) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $candidate->email }}</p>
                    {{-- Score bar --}}
                    <div class="flex items-center gap-2 mt-1.5">
                        <div class="h-1.5 flex-1 bg-slate-100 rounded-full">
                            <div class="h-1.5 {{ $barColor }} rounded-full transition-all"
                                style="width: {{ $percentage }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-slate-700 flex-shrink-0">
                            {{ number_format($response->total_score, 1) }}/{{ number_format($response->max_score, 1) }}
                            ({{ number_format($percentage, 1) }}%)
                        </span>
                    </div>
                </div>

                {{-- View link --}}
                <a href="{{ route('recruitment.candidates.show', $candidate) }}"
                    class="text-blue-600 hover:text-blue-800 text-xs flex-shrink-0">
                    View <i class="fas fa-chevron-right ml-0.5"></i>
                </a>
            </div>
            @empty
            <div class="text-center py-12 text-slate-400">
                <i class="fas fa-inbox text-4xl mb-3 opacity-30"></i>
                <p>No candidates have completed the screening questionnaire yet.</p>
                <p class="text-xs mt-1">Candidates fill out the questions when they apply via the careers page.</p>
            </div>
            @endforelse
        </div>

        {{-- Candidates without screening --}}
        @if($withoutResponses->count())
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-3 text-sm">
                <i class="fas fa-exclamation-triangle text-amber-400 mr-1"></i>
                Candidates without screening responses ({{ $withoutResponses->count() }})
            </h3>
            <p class="text-xs text-slate-500 mb-3">These candidates applied before the screening was set up, or via a manual entry.</p>
            <div class="space-y-1">
                @foreach($withoutResponses as $c)
                <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                    <div>
                        <a href="{{ route('recruitment.candidates.show', $c) }}" class="text-sm font-medium text-slate-700 hover:text-blue-600">{{ $c->name }}</a>
                        <span class="text-xs text-slate-400 ml-2">{{ $c->email }}</span>
                    </div>
                    <span class="badge badge-slate text-xs">{{ ucfirst($c->status) }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Sidebar: Auto-shortlist + criteria summary --}}
    <div class="space-y-4">

        {{-- Auto-shortlist panel --}}
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 mb-2"><i class="fas fa-magic text-purple-500 mr-1"></i> Auto-Shortlist</h3>
            <p class="text-xs text-slate-500 mb-4">
                Automatically mark the top candidates as <strong>Shortlisted</strong> and the rest as <strong>Rejected</strong>.
                Only candidates who completed the screening are affected.
            </p>

            <form method="POST" action="{{ route('recruitment.shortlisting.auto-shortlist', $job) }}"
                onsubmit="return confirm('This will update statuses for all candidates who completed the screening. Continue?')">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Shortlist top</label>
                    <select name="top_n" class="form-input w-full">
                        @foreach([3, 5, 10, 15, 20, 25, 30] as $n)
                        <option value="{{ $n }}" {{ $n == $criteria->top_n ? 'selected' : '' }}>{{ $n }} candidates</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary w-full justify-center text-sm" {{ $withResponses->isEmpty() ? 'disabled' : '' }}>
                    <i class="fas fa-bolt mr-1"></i> Run Auto-Shortlist
                </button>
            </form>
        </div>

        {{-- Criteria overview --}}
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Criteria: {{ $criteria->title }}</h3>
            @if($criteria->description)
            <p class="text-xs text-slate-500 mb-3">{{ $criteria->description }}</p>
            @endif
            <div class="space-y-3">
                @foreach($criteria->questions as $i => $q)
                @php
                    $typeColors = [
                        'multiple_choice' => 'text-blue-600 bg-blue-50',
                        'yes_no'          => 'text-green-700 bg-green-50',
                        'scale'           => 'text-amber-700 bg-amber-50',
                        'text'            => 'text-slate-600 bg-slate-100',
                    ];
                    $typeLabels = [
                        'multiple_choice' => 'MCQ',
                        'yes_no'          => 'Y/N',
                        'scale'           => '1–5',
                        'text'            => 'Text',
                    ];
                @endphp
                <div class="flex items-start gap-2">
                    <span class="text-xs font-bold text-slate-400 w-5 flex-shrink-0">{{ $i+1 }}.</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-700 leading-relaxed">{{ Str::limit($q->question, 80) }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-xs px-1.5 py-0.5 rounded {{ $typeColors[$q->question_type] ?? '' }}">{{ $typeLabels[$q->question_type] ?? $q->question_type }}</span>
                            <span class="text-xs text-slate-400">Weight: <strong class="text-slate-600">{{ $q->weight }}</strong></span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex justify-between text-xs text-slate-500">
                <span>Max possible score: <strong class="text-slate-700">{{ $criteria->maxScore() }}</strong></span>
                <a href="{{ route('recruitment.shortlisting.manage', $job) }}" class="text-blue-600 hover:underline">Edit questions</a>
            </div>
        </div>

        {{-- Top N filter shortcut --}}
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Filter View</h3>
            <form method="GET" action="{{ route('recruitment.shortlisting.results', $job) }}">
                <label class="block text-xs font-medium text-slate-600 mb-1">Show top</label>
                <div class="flex gap-2">
                    <select name="top" class="form-input flex-1">
                        @foreach([5, 10, 15, 20, 25, 30, 50] as $n)
                        <option value="{{ $n }}" {{ $n == $topN ? 'selected' : '' }}>Top {{ $n }}</option>
                        @endforeach
                        <option value="9999" {{ $topN >= 9999 ? 'selected' : '' }}>All</option>
                    </select>
                    <button type="submit" class="btn-secondary text-sm">Go</button>
                </div>
            </form>
        </div>

    </div>
</div>

@endsection
