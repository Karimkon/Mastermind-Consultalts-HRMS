@extends('layouts.app')
@section('title','Check the sheet')
@section('content')

<x-page-header title="This is what would change"
    subtitle="Nothing has been written yet">
    <a href="{{ route('admin.bulk-update.index') }}" class="btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Start again
    </a>
</x-page-header>

@php
    $rows = $report['apply'];
    $problems = $report['problems'];
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="rounded-xl border border-green-200 bg-green-50 p-4">
        <p class="text-2xl font-bold text-green-700">{{ number_format($report['fills']) }}</p>
        <p class="text-xs text-slate-700">blanks filled in</p>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <p class="text-2xl font-bold text-amber-700">{{ number_format($report['overwrites']) }}</p>
        <p class="text-xs text-slate-700">would replace an existing value</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-2xl font-bold text-slate-600">{{ number_format($report['unchanged']) }}</p>
        <p class="text-xs text-slate-700">already correct</p>
    </div>
    <div class="rounded-xl border border-{{ $problems ? 'rose' : 'slate' }}-200 bg-{{ $problems ? 'rose' : 'slate' }}-50 p-4">
        <p class="text-2xl font-bold text-{{ $problems ? 'rose' : 'slate' }}-700">{{ number_format(count($problems)) }}</p>
        <p class="text-xs text-slate-700">rows that could not be used</p>
    </div>
</div>

@if($problems)
<div class="card p-5 mb-5 border-l-4 border-rose-400">
    <h3 class="font-semibold text-slate-800 mb-1">Rows that could not be used</h3>
    <p class="text-xs text-slate-500 mb-3">
        These are skipped. Nothing is created from them — a mistyped staff number must not
        become a second record beside a real person.
    </p>
    <div class="overflow-x-auto max-h-72 overflow-y-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-left sticky top-0">
                <tr>
                    <th class="px-3 py-2 font-semibold text-slate-600">Line</th>
                    <th class="px-3 py-2 font-semibold text-slate-600">Staff no.</th>
                    <th class="px-3 py-2 font-semibold text-slate-600">Why</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($problems as $p)
                <tr>
                    <td class="px-3 py-2 text-slate-500">{{ $p['line'] }}</td>
                    <td class="px-3 py-2 font-mono text-slate-800">{{ $p['emp_number'] ?: '—' }}</td>
                    <td class="px-3 py-2 text-rose-700">{{ $p['reason'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if($rows)
<div class="card p-5 mb-5">
    <h3 class="font-semibold text-slate-800 mb-3">{{ number_format(count($rows)) }} staff record(s) would change</h3>

    <div class="overflow-x-auto max-h-[28rem] overflow-y-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-left sticky top-0">
                <tr>
                    <th class="px-3 py-2 font-semibold text-slate-600">Staff no.</th>
                    <th class="px-3 py-2 font-semibold text-slate-600">Name</th>
                    <th class="px-3 py-2 font-semibold text-slate-600">Change</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($rows as $r)
                <tr class="align-top">
                    <td class="px-3 py-2 font-mono text-slate-800 whitespace-nowrap">{{ $r['emp_number'] }}</td>
                    <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ $r['name'] }}</td>
                    <td class="px-3 py-2">
                        @foreach($r['fills'] as $field => $value)
                            <span class="inline-block mr-3 mb-1">
                                <span class="text-slate-500">{{ $field }}</span>
                                <span class="text-green-700 font-medium">
                                    &rarr; {{ is_scalar($value) ? $value : '(set)' }}
                                </span>
                            </span>
                        @endforeach

                        {{-- Shown apart from the blanks, because replacing a value
                             somebody already entered is a different decision. --}}
                        @foreach($r['overwrites'] as $field => $change)
                            <span class="inline-block mr-3 mb-1">
                                <span class="text-slate-500">{{ $field }}</span>
                                <span class="text-amber-700 font-medium">
                                    {{ $change['from'] }} &rarr; {{ $change['to'] }}
                                </span>
                            </span>
                        @endforeach
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card p-5">
    <form method="POST" action="{{ route('admin.bulk-update.apply') }}">
        @csrf

        @if($report['overwrites'] > 0)
        <label class="flex items-start gap-3 mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 cursor-pointer">
            <input type="checkbox" name="allow_overwrite" value="1" class="mt-0.5 w-4 h-4">
            <span class="text-sm text-amber-900">
                Also replace the {{ $report['overwrites'] }} value(s) that are already filled in.
                <span class="block text-xs text-amber-700 mt-1">
                    Left unticked, the blanks are still filled and only these fields are left alone.
                    A bank account number that is already there was put there by somebody — replacing
                    it with the wrong one pays a stranger.
                </span>
            </span>
        </label>
        @endif

        <div class="flex items-center gap-3">
            <button class="btn-primary">
                <i class="fas fa-check mr-1"></i>
                Apply to {{ number_format(count($rows)) }} record(s)
            </button>
            <a href="{{ route('admin.bulk-update.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</div>
@else
<div class="card p-8 text-center">
    <i class="fas fa-circle-check text-3xl text-slate-300 mb-3 block"></i>
    <p class="text-slate-600 text-sm">Nothing in this sheet would change anything.</p>
    <a href="{{ route('admin.bulk-update.index') }}" class="btn-secondary mt-4 inline-block text-sm">Back</a>
</div>
@endif
@endsection
