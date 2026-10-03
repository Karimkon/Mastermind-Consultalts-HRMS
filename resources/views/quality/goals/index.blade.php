@extends('layouts.app')
@section('title', 'Quality Goals')
@section('content')

@php($statColor = ['assigned'=>'slate','in_progress'=>'amber','completed'=>'emerald','cancelled'=>'slate','overdue'=>'red'])

<x-page-header title="Quality Goals" subtitle="{{ $manage ? 'Assign dated quality goals and track them' : 'Goals assigned to you' }}">
    @if($manage)
    <button onclick="document.getElementById('qg-new').classList.toggle('hidden')" class="btn-primary"><i class="fas fa-plus mr-1"></i> Assign goal</button>
    @endif
</x-page-header>

@if(session('success'))<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

@if($manage)
<div id="qg-new" class="hidden mb-6 rounded-xl bg-white border border-slate-200 p-5">
    <form action="{{ route('quality.goals.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-upload-progress>
        @csrf
        <div class="md:col-span-2"><label class="form-label">Goal title</label><input name="title" required class="form-input"></div>
        <div class="md:col-span-2"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-input"></textarea></div>
        <div><label class="form-label">Assign to</label><select name="assignee_id" required class="form-select"><option value="">Select person</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
        <div><x-file-drop name="files[]" label="Attach files (optional)" :multiple="true" /></div>
        <div><label class="form-label">Start date</label><input type="date" name="start_date" class="form-input"></div>
        <div><label class="form-label">End date</label><input type="date" name="end_date" class="form-input"></div>
        <div class="md:col-span-2 flex justify-end"><button class="btn-primary">Assign goal</button></div>
    </form>
</div>
@endif

<x-data-table>
    <thead><tr class="table-header"><th>Ref</th><th>Goal</th><th>Assignee</th><th>Period</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($goals as $g)
    <tr class="table-row cursor-pointer" onclick="window.location='{{ route('quality.goals.show', $g) }}'">
        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $g->reference }}</td>
        <td class="px-4 py-3 font-medium text-slate-800">{{ $g->title }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $g->assignee?->name ?? '-' }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $g->start_date?->format('d M') }} - {{ $g->end_date?->format('d M Y') }}</td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$g->displayStatus()] ?? 'slate' }}-100 text-{{ $statColor[$g->displayStatus()] ?? 'slate' }}-700">{{ str_replace('_',' ', $g->displayStatus()) }}</span></td>
    </tr>
    @empty
    <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">No goals {{ $manage ? 'yet' : 'assigned to you' }}.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

<div class="mt-4">{{ $goals->links() }}</div>

<x-transfer-progress />

@endsection
