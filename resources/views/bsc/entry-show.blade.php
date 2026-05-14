@extends('layouts.app')
@section('title', 'BSC Entry Detail')
@section('content')
<x-page-header title="BSC Appraisal Entry">
    <a href="{{ url()->previous() }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
    <a href="{{ route('bsc.entries.edit', $entry) }}" class="btn-secondary"><i class="fas fa-edit mr-1"></i> Edit</a>
    @if($entry->status === 'draft')
    <form method="POST" action="{{ route('bsc.entries.submit', $entry) }}" class="inline">
        @csrf
        <button class="btn-primary"><i class="fas fa-paper-plane mr-1"></i> Submit</button>
    </form>
    @elseif($entry->status === 'submitted' && auth()->user()->hasAnyRole(['super-admin','hr-admin','manager','account-manager']))
    <form method="POST" action="{{ route('bsc.entries.approve', $entry) }}" class="inline">
        @csrf
        <button class="btn-primary bg-green-600 hover:bg-green-700"><i class="fas fa-check mr-1"></i> Approve</button>
    </form>
    @endif
</x-page-header>

@if(session('success'))
<div class="alert-success mb-4">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- LEFT COLUMN: KRA + PART I --}}
    <div class="lg:col-span-2 space-y-4">

        {{-- KRA Card --}}
        @php $c = ['financial'=>'blue','customer'=>'green','internal_process'=>'purple','learning_growth'=>'amber'][$entry->kra->perspective] ?? 'slate'; @endphp
        <div class="card p-5 border-t-4 border-{{ $c }}-400">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs text-{{ $c }}-600 uppercase tracking-wide font-semibold mb-1">{{ $entry->kra->perspective_label }}</p>
                    <h3 class="text-xl font-bold text-slate-900">{{ $entry->kra->kra_name }}</h3>
                    @if($entry->kra->objective)
                    <p class="text-sm text-slate-600 mt-1">{{ $entry->kra->objective }}</p>
                    @endif
                </div>
                {!! $entry->status_badge !!}
            </div>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs text-slate-400 mb-1">Target</p>
                    <p class="text-lg font-bold text-slate-700">{{ number_format($entry->kra->target, 0) }} <span class="text-sm font-normal">{{ $entry->kra->unit }}</span></p>
                </div>
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs text-slate-400 mb-1">Actual Achieved</p>
                    <p class="text-lg font-bold text-slate-700">
                        {{ $entry->actual_achieved !== null ? number_format($entry->actual_achieved, 1) . ' ' . $entry->kra->unit : '—' }}
                    </p>
                </div>
                <div class="bg-{{ $entry->target_percent >= 100 ? 'green' : ($entry->target_percent >= 80 ? 'amber' : 'red') }}-50 rounded-lg p-3">
                    <p class="text-xs text-slate-400 mb-1">% of Target</p>
                    <p class="text-lg font-bold text-{{ $entry->target_percent >= 100 ? 'green' : ($entry->target_percent >= 80 ? 'amber' : 'red') }}-600">
                        {{ $entry->target_percent !== null ? number_format($entry->target_percent, 1) . '%' : '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Rating & Weighted Index --}}
        <div class="card p-5">
            <h4 class="font-bold text-slate-800 mb-4">Rating Assessment</h4>
            <div class="flex items-center gap-4 mb-4">
                <div class="flex gap-1">
                    @for($i=1;$i<=5;$i++)
                    <div class="w-10 h-10 rounded-lg border-2 flex items-center justify-center
                                {{ $entry->rating >= $i ? 'bg-amber-400 border-amber-400 text-white' : 'bg-slate-50 border-slate-200 text-slate-300' }}
                                font-bold text-sm">
                        {{ $i }}
                    </div>
                    @endfor
                </div>
                <div>
                    @php $ratingLabels = [1=>'Unacceptable',2=>'Below Target',3=>'On Target',4=>'Above Target',5=>'Exceptional']; @endphp
                    <p class="font-bold text-2xl text-slate-800">{{ $entry->rating ?? '—' }}/5</p>
                    @if($entry->rating)
                    <p class="text-sm text-slate-500">{{ $ratingLabels[$entry->rating] ?? '' }}</p>
                    @endif
                </div>
                <div class="ml-auto text-right bg-blue-50 rounded-lg px-4 py-2">
                    <p class="text-xs text-blue-500 uppercase tracking-wide">Weighted Index</p>
                    <p class="text-2xl font-black text-blue-700">{{ $entry->weighted_index !== null ? number_format($entry->weighted_index, 4) : '—' }}</p>
                    <p class="text-xs text-blue-400">= {{ $entry->rating ?? '?' }} × {{ $entry->kra->weightage }}%</p>
                </div>
            </div>

            @if($entry->employee_comment)
            <div class="bg-slate-50 rounded-lg p-3">
                <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Employee Comment</p>
                <p class="text-sm text-slate-700">{{ $entry->employee_comment }}</p>
            </div>
            @endif
        </div>

        {{-- Part II --}}
        @if($entry->problem_areas || $entry->remedial_actions)
        <div class="card p-5">
            <h4 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fas fa-tools text-amber-400"></i> Part II — Remedial Plan
            </h4>
            @if($entry->problem_areas)
            <div class="mb-3">
                <p class="text-xs font-semibold text-red-600 uppercase mb-1">Problem Areas</p>
                <p class="text-sm text-slate-700 bg-red-50 rounded-lg p-3">{{ $entry->problem_areas }}</p>
            </div>
            @endif
            @if($entry->remedial_actions)
            <div class="mb-3">
                <p class="text-xs font-semibold text-green-600 uppercase mb-1">Remedial Actions</p>
                <p class="text-sm text-slate-700 bg-green-50 rounded-lg p-3">{{ $entry->remedial_actions }}</p>
            </div>
            @endif
            @if($entry->remedial_by_when)
            <div class="flex items-center gap-2 text-sm text-slate-600 mt-2">
                <i class="fas fa-calendar-check text-blue-400"></i>
                Target completion: <strong>{{ $entry->remedial_by_when->format('d M Y') }}</strong>
            </div>
            @endif
        </div>
        @endif

        {{-- Appraiser Comment --}}
        @if($entry->appraiser_comment)
        <div class="card p-5 bg-blue-50 border-blue-200">
            <p class="text-xs font-semibold text-blue-600 uppercase mb-1">Appraiser Comment</p>
            <p class="text-sm text-blue-800">{{ $entry->appraiser_comment }}</p>
        </div>
        @endif
    </div>

    {{-- RIGHT COLUMN: Meta Info --}}
    <div class="space-y-4">
        <div class="card p-4">
            <h4 class="font-semibold text-slate-700 mb-3 text-sm uppercase tracking-wide">Employee</h4>
            <div class="flex items-center gap-3">
                <img src="{{ $entry->employee?->avatar_url }}" class="w-10 h-10 rounded-xl object-cover border border-slate-200">
                <div>
                    <p class="font-semibold text-slate-800 text-sm">{{ $entry->employee?->full_name }}</p>
                    <p class="text-xs text-slate-500">{{ $entry->employee?->designation?->title }}</p>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <h4 class="font-semibold text-slate-700 mb-3 text-sm uppercase tracking-wide">Appraisal Info</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Cycle</span>
                    <span class="font-semibold text-slate-700">{{ $entry->kra->cycle?->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Appraiser Role</span>
                    <span class="font-semibold text-slate-700 capitalize">{{ str_replace('_', ' ', $entry->appraiser_role) }}</span>
                </div>
                @if($entry->appraiser)
                <div class="flex justify-between">
                    <span class="text-slate-500">Appraiser</span>
                    <span class="font-semibold text-slate-700">{{ $entry->appraiser?->full_name }}</span>
                </div>
                @endif
                @if($entry->submitted_at)
                <div class="flex justify-between">
                    <span class="text-slate-500">Submitted</span>
                    <span class="font-semibold text-slate-700">{{ $entry->submitted_at->format('d M Y') }}</span>
                </div>
                @endif
                @if($entry->approved_at)
                <div class="flex justify-between">
                    <span class="text-slate-500">Approved</span>
                    <span class="font-semibold text-green-600">{{ $entry->approved_at->format('d M Y') }}</span>
                </div>
                @endif
            </div>
        </div>
        {{-- Signature Block --}}
        <div class="card p-4">
            <h4 class="font-semibold text-slate-700 mb-3 text-sm uppercase tracking-wide">Signatures</h4>
            <div class="space-y-3">
                <div class="border border-slate-200 rounded-lg p-3">
                    <p class="text-xs text-slate-400 uppercase mb-1">Employee Signature</p>
                    <div class="h-8 border-b border-dashed border-slate-200"></div>
                    <p class="text-xs text-slate-500 mt-1">{{ $entry->employee?->full_name }}</p>
                </div>
                <div class="border border-slate-200 rounded-lg p-3">
                    <p class="text-xs text-slate-400 uppercase mb-1">Appraiser Signature</p>
                    <div class="h-8 border-b border-dashed border-slate-200"></div>
                    <p class="text-xs text-slate-500 mt-1">{{ $entry->appraiser?->full_name ?? 'Appraiser' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
