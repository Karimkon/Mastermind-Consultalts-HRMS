@extends('layouts.app')
@section('title','Overtime Approval')
@section('content')

<x-page-header title="Overtime Approval"
    subtitle="Overtime is only paid once approved here — policy is {{ $dailyCap }}h a day, {{ $weeklyCap }}h a week">
</x-page-header>

@if(session('success'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif

<div class="mb-5 flex items-start gap-3 p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-800 text-sm">
    <i class="fas fa-circle-info mt-0.5"></i>
    <div>
        <p><strong>{{ $pendingCount }}</strong> record(s) are waiting for a decision across all periods.</p>
        <p class="mt-1">Hours above {{ $dailyCap }}h a day or {{ $weeklyCap }}h a week are highlighted, but you can still
        approve them — the figures are a policy guide, not a hard limit. Payroll pays only what is approved here.</p>
    </div>
</div>

<x-filter-bar :action="route('overtime.index')">
    <div><label class="form-label">From</label><input type="date" name="date_from" value="{{ $from }}" class="form-input"></div>
    <div><label class="form-label">To</label><input type="date" name="date_to" value="{{ $to }}" class="form-input"></div>
    <div><label class="form-label">Client</label>
        <select name="client_id" class="form-select w-48 select2">
            <option value="">All Clients</option>
            @foreach($clients as $c)<option value="{{ $c->id }}" {{ request('client_id')==$c->id?'selected':'' }}>{{ $c->company_name }}</option>@endforeach
        </select>
    </div>
    <div><label class="form-label">Status</label>
        <select name="status" class="form-select w-40">
            @foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $v=>$l)
                <option value="{{ $v }}" {{ $status===$v?'selected':'' }}>{{ $l }}</option>
            @endforeach
        </select>
    </div>
</x-filter-bar>

<form method="POST" action="{{ route('overtime.bulk-approve') }}"
      onsubmit="return confirm('Approve the ticked records at the {{ $dailyCap }}h daily cap?')">
@csrf
<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-4 py-3 text-left"><input type="checkbox" onclick="document.querySelectorAll('.ot-check').forEach(c=>c.checked=this.checked)"></th>
        <th class="table-head px-4 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-left">Date</th>
        <th class="table-head px-4 py-3 text-left">Client</th>
        <th class="table-head px-4 py-3 text-right">Recorded</th>
        <th class="table-head px-4 py-3 text-right">Week Total</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
        <th class="table-head px-4 py-3 text-left">Decision</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($logs as $log)
        @php
            $weekKey   = $log->employee_id . '|' . \Carbon\Carbon::parse($log->date)->startOfWeek()->toDateString();
            $weekTotal = $weekTotals[$weekKey] ?? 0;
            $overDay   = $log->exceedsDailyCap();
            $overWeek  = $weekTotal > $weeklyCap;
        @endphp
        <tr class="table-row {{ $overDay || $overWeek ? 'bg-amber-50/50' : '' }}">
            <td class="px-4 py-3">
                @if($log->overtime_status === 'pending')
                <input type="checkbox" name="ids[]" value="{{ $log->id }}" class="ot-check">
                @endif
            </td>
            <td class="px-4 py-3">
                <p class="text-sm font-medium text-slate-800">{{ $log->employee?->full_name ?? '—' }}</p>
                <p class="text-xs text-slate-400">{{ $log->employee?->emp_number }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ $log->date?->format('D d M Y') }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $log->client?->company_name ?? $log->employee?->clients->first()?->company_name ?? '—' }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold {{ $overDay ? 'text-amber-700' : 'text-slate-700' }}">
                {{ number_format($log->overtime_hours, 2) }}h
                @if($overDay)<i class="fas fa-triangle-exclamation ml-1" title="Above the {{ $dailyCap }}h daily policy"></i>@endif
            </td>
            <td class="px-4 py-3 text-right text-sm {{ $overWeek ? 'text-amber-700 font-semibold' : 'text-slate-500' }}">
                {{ number_format($weekTotal, 2) }}h
                @if($overWeek)<i class="fas fa-triangle-exclamation ml-1" title="Above the {{ $weeklyCap }}h weekly policy"></i>@endif
            </td>
            <td class="px-4 py-3">
                @if($log->overtime_status === 'approved')
                    <span class="badge-green">{{ number_format($log->approved_overtime_hours, 2) }}h approved</span>
                    <p class="text-xs text-slate-400 mt-1">by {{ $log->overtimeApprover?->name }}</p>
                @elseif($log->overtime_status === 'rejected')
                    <span class="badge-red">Rejected</span>
                    @if($log->overtime_note)<p class="text-xs text-slate-400 mt-1">{{ $log->overtime_note }}</p>@endif
                @else
                    <span class="badge-yellow">Pending</span>
                @endif
            </td>
            <td class="px-4 py-3">
                @if($log->overtime_status === 'pending')
                <div class="flex items-center gap-1">
                    <input form="ot-{{ $log->id }}" type="number" step="0.25" min="0" max="24" name="hours"
                           value="{{ number_format(min($log->overtime_hours, $dailyCap), 2, '.', '') }}"
                           class="form-input w-20 text-sm" title="Hours to approve">
                    <button form="ot-{{ $log->id }}" formaction="{{ route('overtime.approve', $log) }}"
                            data-loading-label="Approving…"
                            class="px-2 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">
                        Approve
                    </button>
                    <button form="ot-{{ $log->id }}" formaction="{{ route('overtime.reject', $log) }}"
                            data-loading-label="Rejecting…"
                            class="px-2 py-1.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold hover:bg-rose-100">
                        Reject
                    </button>
                </div>
                @else
                <span class="text-xs text-slate-400">{{ $log->overtime_approved_at?->format('d M Y') }}</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="8" class="py-12 text-center">
            <i class="fas fa-clock text-3xl text-slate-300 mb-3 block"></i>
            <p class="text-slate-500 text-sm">No overtime records {{ $status !== 'all' ? "with status \"{$status}\"" : '' }} in this period.</p>
            <p class="text-slate-400 text-xs mt-1">Overtime appears here when someone clocks out after more than 8 hours.</p>
        </td></tr>
        @endforelse
    </tbody>
</x-data-table>

@if($logs->where('overtime_status','pending')->count())
<div class="mt-4">
    <button class="btn-primary" data-loading-label="Approving overtime…" data-loading-overlay><i class="fas fa-check-double mr-1"></i>
        Approve Ticked at {{ $dailyCap }}h Cap
    </button>
</div>
@endif
</form>

{{-- Per-row forms live outside the bulk form so the two never nest. --}}
@foreach($logs as $log)
    @if($log->overtime_status === 'pending')
    <form id="ot-{{ $log->id }}" method="POST" class="hidden">@csrf</form>
    @endif
@endforeach

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
