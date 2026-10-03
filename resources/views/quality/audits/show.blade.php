@extends('layouts.app')
@section('title', $audit->reference)
@section('content')

@php($statColor = ['planned' => 'slate', 'in_progress' => 'amber', 'completed' => 'emerald'])
@php($resColor = ['conform' => 'emerald', 'observation' => 'blue', 'minor_nc' => 'amber', 'major_nc' => 'red', 'na' => 'slate'])
@php($scoreColor = $audit->score === null ? 'slate' : ($audit->score >= 90 ? 'emerald' : ($audit->score >= 75 ? 'amber' : 'red')))

<x-page-header title="{{ $audit->reference }}" subtitle="{{ $audit->title }}">
    <a href="{{ route('quality.audits.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 p-4"><p class="text-xs text-slate-400 uppercase">Status</p><p class="mt-1"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$audit->status] ?? 'slate' }}-100 text-{{ $statColor[$audit->status] ?? 'slate' }}-700">{{ str_replace('_',' ', $audit->status) }}</span></p></div>
    <div class="rounded-xl bg-white border border-slate-200 p-4"><p class="text-xs text-slate-400 uppercase">Scope</p><p class="mt-1 text-sm font-medium text-slate-700">{{ $audit->scope === 'all' ? 'All functions' : ucwords(str_replace('_',' ', $audit->scope)) }}</p></div>
    <div class="rounded-xl bg-white border border-slate-200 p-4"><p class="text-xs text-slate-400 uppercase">Auditor</p><p class="mt-1 text-sm font-medium text-slate-700">{{ $audit->auditor?->name ?? '-' }}</p></div>
    <div class="rounded-xl bg-white border border-slate-200 p-4"><p class="text-xs text-slate-400 uppercase">Planned</p><p class="mt-1 text-sm font-medium text-slate-700">{{ $audit->planned_date?->format('d M Y') ?? '-' }}</p></div>
    <div class="rounded-xl bg-white border border-slate-200 p-4"><p class="text-xs text-slate-400 uppercase">Score</p><p class="mt-1 text-xl font-bold text-{{ $scoreColor }}-600">{{ $audit->score !== null ? rtrim(rtrim(number_format($audit->score,1),'0'),'.').'%' : '-' }}</p></div>
</div>

@if($audit->status === 'completed')
<div class="mb-4 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600"><i class="fas fa-lock mr-1"></i> This audit is closed. Findings were raised as non-conformities where applicable.</div>
@endif

<form action="{{ route('quality.audits.update', $audit) }}" method="POST">
    @csrf @method('PUT')
    <x-data-table>
        <thead><tr class="table-header"><th>Standard</th><th>Checklist item</th><th>Result</th><th>Notes / Evidence</th></tr></thead>
        <tbody>
        @foreach($audit->items as $item)
        <tr class="table-row align-top">
            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $item->standard?->code ?? '-' }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ $item->question }}</td>
            <td class="px-4 py-3">
                @if($audit->status === 'completed')
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $resColor[$item->result] ?? 'slate' }}-100 text-{{ $resColor[$item->result] ?? 'slate' }}-700">{{ $item->result ? str_replace('_',' ', $item->result) : 'not scored' }}</span>
                @else
                <select name="items[{{ $item->id }}][result]" class="form-select text-sm">
                    <option value="">-</option>
                    @foreach(['conform'=>'Conform','observation'=>'Observation','minor_nc'=>'Minor NC','major_nc'=>'Major NC','na'=>'N/A'] as $k=>$v)
                    <option value="{{ $k }}" {{ $item->result === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
                @endif
            </td>
            <td class="px-4 py-3">
                @if($audit->status === 'completed')
                    <p class="text-sm text-slate-600">{{ $item->notes }}</p>
                    @if($item->evidence)<p class="text-xs text-slate-400 mt-1">Evidence: {{ $item->evidence }}</p>@endif
                @else
                    <input name="items[{{ $item->id }}][notes]" value="{{ $item->notes }}" placeholder="Notes" class="form-input text-sm mb-1">
                    <input name="items[{{ $item->id }}][evidence]" value="{{ $item->evidence }}" placeholder="Evidence (reference)" class="form-input text-sm">
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
    </x-data-table>

    @if($audit->status !== 'completed')
    <div class="flex items-center justify-between mt-4">
        <button class="btn-secondary"><i class="fas fa-floppy-disk mr-1"></i> Save progress</button>
    </div>
    @endif
</form>

@if($audit->status !== 'completed')
<div class="mt-6 rounded-xl bg-white border border-slate-200 p-5">
    <h3 class="font-semibold text-slate-700 mb-3">Close the audit</h3>
    <p class="text-sm text-slate-500 mb-3">Scoring is the average of all scored items (Conform 100, Observation 85, Minor NC 50, Major NC 0; N/A excluded). Minor and major findings are raised as non-conformities automatically. Save your results first.</p>
    <form action="{{ route('quality.audits.complete', $audit) }}" method="POST">
        @csrf
        <textarea name="summary" rows="2" class="form-input mb-3" placeholder="Audit summary / conclusion (optional)">{{ $audit->summary }}</textarea>
        <button class="btn-primary" onclick="return confirm('Close this audit and raise non-conformities from the findings?')"><i class="fas fa-flag-checkered mr-1"></i> Complete audit</button>
    </form>
</div>
@elseif($audit->summary)
<div class="mt-6 rounded-xl bg-white border border-slate-200 p-5">
    <h3 class="font-semibold text-slate-700 mb-2">Summary</h3>
    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $audit->summary }}</p>
</div>
@endif

@endsection
