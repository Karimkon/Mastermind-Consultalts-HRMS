@extends('layouts.app')
@section('title', 'Performance Review')
@section('content')
<x-page-header title="Performance Review" :subtitle="$review->employee->full_name">
    <a href="{{ route('performance.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 card p-6">
        <div class="flex items-center gap-4 mb-6">
            <img src="{{ $review->employee->avatar_url }}" class="w-14 h-14 rounded-xl object-cover">
            <div>
                <h3 class="font-semibold text-slate-800">{{ $review->employee->full_name }}</h3>
                <p class="text-sm text-slate-500">{{ $review->employee->designation->title ?? '' }} — {{ $review->employee->department->name ?? '' }}</p>
            </div>
            <div class="ml-auto text-right">
                <p class="text-3xl font-bold text-blue-600">{{ $review->total_score }}</p>
                <p class="text-xs text-slate-500">Total Score</p>
            </div>
        </div>
        <div class="space-y-3">
            @php $ratings = $review->ratings ?? []; @endphp
            @foreach($kpis as $kpi)
            @php $score = $ratings[$kpi->id] ?? 0; @endphp
            <div class="flex items-center gap-3">
                <div class="w-48 text-sm text-slate-700">{{ $kpi->name }}</div>
                <div class="flex-1 h-2 bg-slate-100 rounded-full">
                    <div class="h-2 rounded-full bg-blue-500" style="width:{{ ($score/5)*100 }}%"></div>
                </div>
                <div class="w-8 text-sm font-medium text-slate-700 text-right">{{ $score }}/5</div>
            </div>
            @endforeach
        </div>
        @if($review->comments)
        <div class="mt-6 pt-4 border-t border-slate-100">
            <h4 class="text-sm font-semibold text-slate-700 mb-2">Comments</h4>
            <p class="text-sm text-slate-600">{{ $review->comments }}</p>
        </div>
        @endif
    </div>
    <div class="space-y-4">
        <div class="card p-5 space-y-3 text-sm">
            <h3 class="font-semibold text-slate-700">Review Info</h3>
            <div class="flex justify-between"><span class="text-slate-500">Reviewer</span><span>{{ $review->reviewer->full_name ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Cycle</span><span>{{ $review->cycle->name ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Type</span><span class="badge badge-blue">{{ ucfirst($review->type) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Date</span><span>{{ $review->created_at->format('M d, Y') }}</span></div>
        </div>
    </div>
</div>
@endsection