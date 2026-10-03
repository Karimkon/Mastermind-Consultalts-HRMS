@extends('layouts.app')
@section('title', 'Non-conformities')
@section('content')

@php
    $sevColor = ['critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'slate'];
    $statColor = ['open' => 'red', 'investigating' => 'amber', 'resolved' => 'emerald', 'closed' => 'slate', 'risk_accepted' => 'violet'];
@endphp

<x-page-header title="Non-conformities" subtitle="Quality issues raised across the HRMS">
    <button onclick="document.getElementById('nc-new').classList.toggle('hidden')" class="btn-primary"><i class="fas fa-plus mr-1"></i> Raise manually</button>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div id="nc-new" class="hidden mb-6 rounded-xl bg-white border border-slate-200 p-5">
    <form action="{{ route('quality.nonconformities.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @csrf
        <div class="md:col-span-2"><label class="form-label">Title</label><input name="title" required class="form-input w-full" placeholder="What is non-conforming?"></div>
        <div class="md:col-span-2"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-input w-full"></textarea></div>
        <div>
            <label class="form-label">HR function</label>
            <select name="hr_function" class="form-input w-full">
                @foreach(['employee_central'=>'Employee Central','payroll'=>'Payroll','recruitment'=>'Recruitment','attendance'=>'Attendance','leave'=>'Leave','performance'=>'Performance','compliance'=>'Compliance'] as $k=>$v)
                <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Severity</label>
            <select name="severity" class="form-input w-full"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select>
        </div>
        <div><label class="form-label">Due date</label><input type="date" name="due_date" class="form-input w-full"></div>
        <div class="md:col-span-2 flex justify-end"><button class="btn-primary">Raise non-conformity</button></div>
    </form>
</div>

{{-- Filters --}}
<form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    @foreach(['open'=>'Open','investigating'=>'Investigating','resolved'=>'Resolved','closed'=>'Closed','all'=>'All'] as $val=>$label)
    <a href="{{ route('quality.nonconformities.index', array_filter(['status'=>$val,'severity'=>$severity,'function'=>$function])) }}"
       class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $val ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $label }}</a>
    @endforeach
    <select name="severity" onchange="this.form.submit()" class="ml-auto rounded-lg border-slate-200 text-sm">
        <option value="">Any severity</option>
        @foreach(['critical','high','medium','low'] as $s)<option value="{{ $s }}" {{ $severity === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <input type="hidden" name="status" value="{{ $status }}">
</form>

<x-data-table>
    <thead><tr class="table-header"><th>Ref</th><th>Issue</th><th>Function</th><th>Severity</th><th>Status</th><th>Actions</th><th>Detected</th></tr></thead>
    <tbody>
    @forelse($items as $nc)
    <tr class="table-row cursor-pointer" onclick="window.location='{{ route('quality.nonconformities.show', $nc) }}'">
        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $nc->reference }}</td>
        <td class="px-4 py-3"><p class="font-medium text-slate-800">{{ $nc->title }}</p>@if($nc->subject_label)<p class="text-xs text-slate-400">{{ $nc->subject_label }}</p>@endif</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ ucwords(str_replace('_',' ', $nc->hr_function)) }}</td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $sevColor[$nc->severity] ?? 'slate' }}-100 text-{{ $sevColor[$nc->severity] ?? 'slate' }}-700">{{ $nc->severity }}</span></td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$nc->status] ?? 'slate' }}-100 text-{{ $statColor[$nc->status] ?? 'slate' }}-700">{{ str_replace('_',' ', $nc->status) }}</span></td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $nc->actions->count() }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $nc->detected_at?->diffForHumans() }}</td>
    </tr>
    @empty
    <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-emerald-600"><i class="fas fa-check-circle mr-1"></i> Nothing here - no non-conformities match.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

<div class="mt-4">{{ $items->links() }}</div>

@endsection
