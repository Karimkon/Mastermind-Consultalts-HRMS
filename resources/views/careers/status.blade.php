@extends('careers.layout')
@section('title', 'Your application — Mastermind Careers')
@section('content')

@if(session('info'))
<div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-5 py-4 mb-6 text-sm">
    <i class="fas fa-circle-info mr-1"></i>{{ session('info') }}
</div>
@endif

<div class="grid lg:grid-cols-3 gap-6">

    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold">Application</p>
                    <h1 class="text-lg font-bold text-slate-800 mt-0.5">{{ $candidate->jobPosting?->title ?? 'Position' }}</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ $candidate->name }} &middot; applied {{ $candidate->created_at?->format('d M Y') }}
                    </p>
                </div>
                <span class="flex-shrink-0 text-xs font-bold tracking-widest bg-slate-900 text-white rounded-lg px-3 py-2">
                    {{ $candidate->tracking_code }}
                </span>
            </div>

            @include('careers._progress')
        </div>

        {{-- The trail. Dates only, and the wording the applicant was already
             sent - nothing a recruiter wrote for internal eyes. --}}
        @if($candidate->statusEvents->count())
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="font-semibold text-slate-800 text-sm mb-4">Updates</h2>
            <div class="space-y-4">
                @foreach($candidate->statusEvents->sortByDesc('created_at') as $event)
                <div class="flex gap-3">
                    <div class="flex-shrink-0 w-1 rounded bg-slate-200"></div>
                    <div>
                        <p class="text-xs text-slate-400">{{ $event->created_at?->format('d M Y, H:i') }}</p>
                        <p class="text-sm text-slate-700 mt-0.5">{{ $event->message }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div class="space-y-6">
        @if($response && $response->max_score > 0)
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 text-sm mb-3">Your assessment score</h2>
            <p class="text-3xl font-black
                @if($response->percentage >= 70) text-green-600
                @elseif($response->percentage >= 50) text-amber-600
                @else text-slate-600 @endif">{{ rtrim(rtrim(number_format($response->percentage, 1), '0'), '.') }}%</p>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden mt-2">
                <div class="h-full rounded-full
                    @if($response->percentage >= 70) bg-green-500
                    @elseif($response->percentage >= 50) bg-amber-500
                    @else bg-slate-400 @endif"
                    style="width: {{ min(100, max(2, $response->percentage)) }}%"></div>
            </div>
            <p class="text-xs text-slate-800 font-semibold mt-2">{{ rtrim(rtrim(number_format($response->total_score, 1), '0'), '.') }} of {{ rtrim(rtrim(number_format($response->max_score, 1), '0'), '.') }} marks</p>
            <p class="text-xs text-slate-400 mt-0.5">From the screening questions you answered.</p>
        </div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 text-sm mb-3">This position</h2>
            <div class="flex items-baseline gap-2">
                <p class="text-2xl font-black text-slate-800">{{ $applicantCount }}</p>
                <p class="text-xs text-slate-500">{{ Str::plural('person', $applicantCount) }} applied</p>
            </div>
            @if($candidate->jobPosting?->vacancies)
            <p class="text-xs text-slate-400 mt-1">
                {{ $candidate->jobPosting->vacancies }} {{ Str::plural('vacancy', $candidate->jobPosting->vacancies) }} available
            </p>
            @endif
        </div>

        @if(!empty($topCategories))
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 text-sm mb-1">Most applied for</h2>
            <p class="text-xs text-slate-400 mb-3">Share of all applications received.</p>
            <div class="space-y-3">
                @foreach($topCategories as $cat)
                <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-medium text-slate-700">{{ $cat['label'] }}</span>
                        <span class="text-slate-500 font-semibold">{{ $cat['percent'] }}%</span>
                    </div>
                    <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full" style="width: {{ min(100, max(2, $cat['percent'])) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($candidate->documents->count())
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 text-sm mb-3">Your documents</h2>
            <div class="space-y-2">
                @foreach($candidate->documents as $doc)
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <i class="fas fa-file text-slate-300"></i>
                    <span class="flex-1 truncate">{{ $doc->label }}</span>
                    @if($doc->size_label)<span class="text-slate-400">{{ $doc->size_label }}</span>@endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
