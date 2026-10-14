@extends('layouts.app')
@section('title','Role Archive')
@section('content')

{{--
    Every post, and everyone who has ever held it.

    Roles & Permissions answers "what can this role do". This answers "who has
    been our HR", which the permission tables cannot: removing a role deletes
    the row, so the only record of a departed holder is the one kept here.
--}}

<x-page-header title="Role Archive" subtitle="Who holds each post now, and who held it before">
    <a href="{{ route('admin.roles.index') }}" class="btn-secondary text-xs py-1.5 px-3">
        <i class="fas fa-sliders-h mr-1"></i>Roles &amp; Permissions
    </a>
</x-page-header>

@if ($undated > 0)
    <div class="mb-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
        <i class="fas fa-circle-info mr-1"></i>
        {{ $undated }} current appointment(s) have no start date. The archive began on
        {{ $openedOn?->format('j M Y') ?? 'the day it was introduced' }}; nothing in the
        system recorded when those roles were first given, and an invented date in an
        audit trail is worse than an admitted gap. Everything from here on is dated.
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    @foreach ($roles as $role)
        @php($spells = $history->get($role->name, collect()))
        @php($current = $spells->whereNull('ended_at'))
        @php($former = $spells->whereNotNull('ended_at'))

        <div class="card p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-semibold text-slate-800">
                        {{ ucfirst(str_replace('-', ' ', $role->name)) }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $current->count() }} holding it now ·
                        {{ $spells->count() }} {{ Str::plural('appointment', $spells->count()) }} on record
                    </p>
                </div>
                <a href="{{ route('admin.roles.edit', $role) }}"
                   class="text-xs text-slate-400 hover:text-slate-600" title="Permissions for this role">
                    <i class="fas fa-sliders-h"></i>
                </a>
            </div>

            @if ($spells->isEmpty())
                <p class="text-sm text-slate-400">Nobody has held this role.</p>
            @else
                {{--
                    Current holders are capped. 1,242 people hold 'employee',
                    and listing them turned this page into half a megabyte of
                    names nobody came here to read. Former holders below are
                    never capped -- those are the archive, and there are a
                    handful of them.
                --}}
                @foreach ($current->take(12) as $a)
                    <div class="flex items-center justify-between gap-3 text-sm border-l-2 border-green-400 pl-3 py-1 mb-1">
                        <span class="font-medium text-slate-800">{{ $a->holderName() }}</span>
                        <span class="text-xs text-slate-500">{{ $a->periodLabel() }}</span>
                    </div>
                @endforeach

                @if ($current->count() > 12)
                    <p class="text-xs text-slate-500 pl-3 py-1">
                        and {{ number_format($current->count() - 12) }} more holding it now
                    </p>
                @endif

                {{-- held it before --}}
                @if ($former->isNotEmpty())
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mt-3 mb-1">
                        Previously
                    </p>
                    @foreach ($former as $a)
                        <div class="flex items-center justify-between gap-3 text-sm border-l-2 border-slate-200 pl-3 py-1">
                            <span class="text-slate-600">{{ $a->holderName() }}</span>
                            <span class="text-xs text-slate-400">{{ $a->periodLabel() }}</span>
                        </div>
                    @endforeach
                @endif
            @endif
        </div>
    @endforeach
</div>

@endsection
