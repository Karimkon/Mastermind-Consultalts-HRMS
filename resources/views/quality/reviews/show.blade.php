@extends('layouts.app')
@section('title', $review->reference)
@section('content')

@php($statColor = ['draft' => 'amber', 'completed' => 'emerald'])

<x-page-header title="{{ $review->reference }}" subtitle="{{ $review->title }}">
    <a href="{{ route('quality.reviews.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <form action="{{ route('quality.reviews.update', $review) }}" method="POST" class="rounded-xl bg-white border border-slate-200 p-5 space-y-4">
            @csrf @method('PUT')
            <div><label class="form-label">Summary of quality performance</label><textarea name="summary" rows="5" class="form-input" placeholder="Scores, trends, recurring issues, audit outcomes...">{{ $review->summary }}</textarea></div>
            <div><label class="form-label">Decisions & actions agreed</label><textarea name="decisions" rows="5" class="form-input" placeholder="Resource decisions, process changes, owners and deadlines...">{{ $review->decisions }}</textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="draft" {{ $review->status === 'draft' ? 'selected' : '' }}>Draft</option><option value="completed" {{ $review->status === 'completed' ? 'selected' : '' }}>Completed</option></select>
                </div>
                <div><label class="form-label">Held on</label><input type="date" name="held_on" value="{{ $review->held_on?->format('Y-m-d') }}" class="form-input"></div>
            </div>
            <div class="flex justify-end"><button class="btn-primary">Save review</button></div>
        </form>
    </div>

    <div class="space-y-4">
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</p>
            <p class="mt-1"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$review->status] ?? 'slate' }}-100 text-{{ $statColor[$review->status] ?? 'slate' }}-700">{{ $review->status }}</span></p>
        </div>
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Score at opening</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $review->overall_score !== null ? rtrim(rtrim(number_format($review->overall_score,1),'0'),'.').'%' : '-' }}</p>
        </div>
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Context now</p>
            <p class="text-sm text-slate-600 mt-2">Current score: <span class="font-semibold">{{ $latest?->score !== null ? $latest->score.'%' : 'n/a' }}</span></p>
            <p class="text-sm text-slate-600">Open non-conformities: <span class="font-semibold">{{ $openNc }}</span></p>
            <p class="text-xs text-slate-400 mt-1">Chaired by {{ $review->chair?->name ?? 'n/a' }}</p>
        </div>
    </div>
</div>

@endsection
