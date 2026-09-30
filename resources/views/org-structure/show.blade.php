@extends('layouts.app')
@section('title', $position->title)

@section('content')
<div class="mb-4">
    <a href="{{ route('org-structure.index') }}" class="text-sm text-blue-600 hover:underline">
        <i class="fas fa-arrow-left"></i> Organisational Structure
    </a>
</div>

<div class="card p-6 mb-5">
    <h1 class="text-2xl font-bold text-slate-800">{{ $position->title }}</h1>
    @if($position->client)
        <p class="text-sm text-slate-500 mt-1">Client site — {{ $position->client->company_name }}</p>
    @endif

    {{-- The whole line of authority above this box, top first. This is the
         answer to "who do I ultimately report to", which is the question the
         chart exists for. --}}
    @if(count($chain))
    <div class="mt-4 pt-4 border-t border-slate-100">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Reports up through</p>
        <div class="flex flex-wrap items-center gap-1.5 text-sm">
            @foreach($chain as $step)
                <a href="{{ route('org-structure.show', $step) }}"
                   class="px-2 py-1 rounded bg-slate-100 text-slate-700 hover:bg-blue-50 hover:text-blue-700">
                    {{ $step->title }}
                </a>
                <i class="fas fa-chevron-right text-[10px] text-slate-300"></i>
            @endforeach
            <span class="px-2 py-1 rounded bg-blue-600 text-white font-medium">{{ $position->title }}</span>
        </div>
    </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="card p-5">
        <h2 class="font-semibold text-slate-800 mb-3">
            In this position
            <span class="text-slate-400 font-normal">({{ $position->employees->count() }})</span>
        </h2>

        @forelse($position->employees as $employee)
            <div class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
                <div class="flex items-center gap-3">
                    <img src="{{ $employee->avatar_url }}" alt=""
                         class="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0">
                    <div>
                    <a href="{{ route('employees.show', $employee) }}"
                       class="text-sm font-medium text-slate-800 hover:text-blue-600">{{ $employee->full_name }}</a>
                    <p class="text-xs text-slate-500">
                        {{ $employee->designation?->title ?? 'No designation' }}
                        @if($employee->department) • {{ $employee->department->name }} @endif
                    </p>
                    </div>
                </div>
                <span class="text-xs text-slate-400">{{ $employee->emp_number }}</span>
            </div>
        @empty
            <p class="text-sm text-amber-600"><i class="fas fa-circle-exclamation"></i> This position is vacant.</p>
        @endforelse
    </div>

    <div class="card p-5">
        <h2 class="font-semibold text-slate-800 mb-3">
            Reports to this position
            <span class="text-slate-400 font-normal">({{ $position->children->count() }})</span>
        </h2>

        @forelse($position->children as $child)
            <a href="{{ route('org-structure.show', $child) }}"
               class="flex items-center gap-2 py-2 border-b border-slate-50 last:border-0 group">
                <i class="fas {{ $child->client_id ? 'fa-building text-emerald-500' : 'fa-user-tie text-blue-500' }} text-xs"></i>
                <span class="text-sm text-slate-800 group-hover:text-blue-600">{{ $child->title }}</span>
            </a>
        @empty
            <p class="text-sm text-slate-400">Nothing reports to this position.</p>
        @endforelse
    </div>
</div>
@endsection
