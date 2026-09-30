@extends('layouts.app')
@section('title', 'Rating Scales')

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Rating Scales</h1>
        <p class="text-sm text-slate-500">
            How many points a KPI can score, and what each score is called.
        </p>
    </div>
    <a href="{{ route('admin.rating-scales.create') }}" class="btn-primary text-sm">
        <i class="fas fa-plus mr-1"></i> New scale
    </a>
</div>

{{-- Reuses the existing generic flash partial rather than adding a second copy. --}}
@include('admin.blog.partials.flash')

<div class="space-y-4">
    @forelse($scales as $scale)
    <div class="card p-4">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="font-semibold text-slate-800">
                    {{ $scale->name }}
                    @if($scale->is_default)
                        <span class="ml-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs">Default</span>
                    @endif
                    @unless($scale->is_active)
                        <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 text-xs">Inactive</span>
                    @endunless
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $scale->max_points }} points ·
                    {{ $scale->appraisals_count }} appraisal(s) using it
                    @if($scale->description) · {{ $scale->description }} @endif
                </p>
            </div>

            <div class="flex items-center gap-2">
                @unless($scale->is_default)
                    <form method="POST" action="{{ route('admin.rating-scales.default', $scale) }}">
                        @csrf
                        <button class="btn-secondary text-xs">Make default</button>
                    </form>
                @endunless
                <a href="{{ route('admin.rating-scales.edit', $scale) }}" class="btn-secondary text-xs">Edit</a>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            @foreach($scale->bands as $band)
                <div class="px-2 py-1 rounded border border-slate-200 bg-slate-50 text-xs">
                    <span class="font-semibold text-slate-700">{{ $band->points }} = {{ $band->label }}</span>
                    <span class="text-slate-400 ml-1">{{ $band->range_label ?: $band->min_percent . '%+' }}</span>
                </div>
            @endforeach
        </div>

        {{-- A scale whose bands do not cover every point would leave scores
             unnamed on the card, so it is called out here rather than
             discovered by whoever is mid-appraisal. --}}
        @unless($scale->isComplete())
            <p class="mt-2 text-xs text-amber-600">
                <i class="fas fa-triangle-exclamation"></i>
                This scale has {{ $scale->bands->count() }} band(s) for {{ $scale->max_points }} points.
            </p>
        @endunless
    </div>
    @empty
        <div class="card p-10 text-center text-slate-400">
            <i class="fas fa-list-ol text-3xl mb-3"></i>
            <p class="text-slate-600 font-medium">No rating scales yet.</p>
            <p class="text-sm mt-1">
                Run <code class="px-1.5 py-0.5 bg-slate-100 rounded">php artisan hrms:seed-rating-scales</code>
                or create one.
            </p>
        </div>
    @endforelse
</div>
@endsection
