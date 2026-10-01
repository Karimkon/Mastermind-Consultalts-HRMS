@extends('careers.layout')
@section('title', 'Application submitted — Mastermind Careers')
@section('content')

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="bg-green-50 border-b border-green-100 px-6 py-7 text-center">
        <div class="w-14 h-14 rounded-full bg-green-500 text-white flex items-center justify-center mx-auto mb-3 text-2xl">
            <i class="fas fa-check"></i>
        </div>
        <h1 class="text-xl font-bold text-slate-800">Your application has been received</h1>
        <p class="text-sm text-slate-600 mt-1">{{ $candidate->jobPosting?->title }}</p>
    </div>

    <div class="px-6 py-6">
        {{-- The reference matters more than anything else on this page: it is
             how they come back to it, and nobody can look an application up
             without it. --}}
        <div class="bg-slate-900 rounded-xl px-5 py-4 text-center mb-6">
            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Your reference</p>
            <p class="text-2xl font-black text-white tracking-[0.2em]">{{ $candidate->tracking_code }}</p>
            <p class="text-xs text-slate-400 mt-2">Keep this. You will need it to check your application.</p>
        </div>

        @if($response && $response->max_score > 0)
        {{-- The applicant sees their own screening result. They answered the
             questions; hiding the mark from them while showing it to the
             recruiter was never defensible. --}}
        <div class="border border-slate-200 rounded-xl p-5 mb-6">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <h2 class="font-semibold text-slate-800 text-sm">Your initial assessment</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Scored automatically from the screening questions you answered.</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-2xl font-black
                        @if($response->percentage >= 70) text-green-600
                        @elseif($response->percentage >= 50) text-amber-600
                        @else text-slate-600 @endif">{{ rtrim(rtrim(number_format($response->percentage, 1), '0'), '.') }}%</p>
                    <p class="text-[11px] text-slate-400">{{ rtrim(rtrim(number_format($response->total_score, 1), '0'), '.') }} of {{ rtrim(rtrim(number_format($response->max_score, 1), '0'), '.') }} marks</p>
                </div>
            </div>
            <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all
                    @if($response->percentage >= 70) bg-green-500
                    @elseif($response->percentage >= 50) bg-amber-500
                    @else bg-slate-400 @endif"
                    style="width: {{ min(100, max(2, $response->percentage)) }}%"></div>
            </div>
            <p class="text-xs text-slate-500 mt-3">
                This score is one part of how applications are reviewed. Written answers and your documents
                are read by a person, so the score on its own does not decide the outcome.
            </p>
        </div>
        @endif

        <h2 class="font-semibold text-slate-800 text-sm mb-4">What happens next</h2>
        @include('careers._progress')

        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('careers.status.show', $candidate->tracking_code) }}"
               class="flex-1 text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm py-3 rounded-xl transition">
                <i class="fas fa-location-crosshairs mr-1"></i> Track this application
            </a>
            <a href="{{ route('careers.index') }}"
               class="flex-1 text-center border border-slate-200 hover:border-slate-300 text-slate-700 font-semibold text-sm py-3 rounded-xl transition">
                <i class="fas fa-th-list mr-1"></i> Browse more jobs
            </a>
        </div>

        @if($candidate->documents->count())
        <div class="mt-6 pt-5 border-t border-slate-100">
            <p class="text-xs font-semibold text-slate-600 mb-2">You attached {{ $candidate->documents->count() }} {{ Str::plural('document', $candidate->documents->count()) }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach($candidate->documents as $doc)
                <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 rounded-lg px-2.5 py-1 text-xs">
                    <i class="fas fa-paperclip text-slate-400"></i>{{ $doc->label }}
                    @if($doc->size_label)<span class="text-slate-400">{{ $doc->size_label }}</span>@endif
                </span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
