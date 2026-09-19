@extends('layouts.app')
@section('title','Public Holiday Pay')
@section('content')

<x-page-header title="Public Holiday Pay"
    subtitle="Decide which public holidays are paid, and record who worked them">
</x-page-header>

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

{{-- The nag. A holiday that has already passed with no decision means nobody
     gets paid for it, so it must be impossible to walk past. --}}
@if($awaiting->isNotEmpty())
<div class="mb-5 p-4 bg-amber-50 border border-amber-300 rounded-xl">
    <p class="font-semibold text-amber-900">
        <i class="fas fa-bell mr-1"></i>
        {{ $awaiting->count() }} public holiday{{ $awaiting->count() === 1 ? '' : 's' }} still need a decision
    </p>
    <p class="text-sm text-amber-800 mt-1">
        These have already passed. Until one is approved, nobody is paid for it — and anyone who worked it
        is paid at the normal rate instead of double.
    </p>
    <div class="flex flex-wrap gap-2 mt-3">
        @foreach($awaiting as $h)
            <span class="px-2.5 py-1 rounded-full bg-white border border-amber-300 text-xs font-medium text-amber-900">
                {{ $h->name }} · {{ $h->date->format('d M Y') }}
            </span>
        @endforeach
    </div>
</div>
@endif

<div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
    <p class="font-semibold"><i class="fas fa-circle-info mr-1"></i> How this pays out</p>
    <ul class="list-disc list-inside mt-1 space-y-0.5 text-xs">
        <li><strong>Monthly staff</strong> — nothing changes, their salary already covers the holiday.</li>
        <li><strong>Daily / hourly staff, holiday approved, did not work</strong> — paid one day at their normal rate.</li>
        <li><strong>Daily / hourly staff, holiday approved, worked it</strong> — paid <strong>double</strong> (15,000/day becomes 30,000).</li>
        <li><strong>Worked but the holiday was never approved</strong> — paid the normal rate only, and flagged.</li>
    </ul>
</div>

<x-filter-bar :action="route('holiday-pay.index')">
    <div><label class="form-label">Client</label>
        <select name="client_id" class="form-select w-56 select2">
            @if($canApprove)<option value="">All clients (company-wide)</option>@endif
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="form-label">Year</label>
        <select name="year" class="form-select w-28">
            @for($y = now()->year - 2; $y <= now()->year + 2; $y++)
                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
    </div>
    <div><label class="form-label">Month</label>
        <select name="month" class="form-select w-36">
            <option value="">Whole year</option>
            @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
            @endfor
        </select>
    </div>
</x-filter-bar>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Holiday</th>
        <th class="table-head px-4 py-3 text-left">Date</th>
        <th class="table-head px-4 py-3 text-left">Type</th>
        <th class="table-head px-4 py-3 text-center">Worked It</th>
        <th class="table-head px-4 py-3 text-left">Pay Decision</th>
        <th class="table-head px-4 py-3 text-left">Actions</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($holidays as $holiday)
        @php
            $approval = $approvals->get($holiday->id);
            $status   = $approval?->status ?? 'pending';
            $past     = $holiday->date->isPast();
            $worked   = $workedCounts[$holiday->id] ?? 0;
        @endphp
        <tr class="table-row {{ $past && $status === 'pending' ? 'bg-amber-50/50' : '' }}">
            <td class="px-6 py-3">
                <p class="text-sm font-medium text-slate-800">{{ $holiday->name }}</p>
                @if($approval?->decider)
                    <p class="text-xs text-slate-400">{{ $status === 'approved' ? 'Approved' : 'Rejected' }}
                        by {{ $approval->decider->name }} · {{ $approval->decided_at?->format('d M Y') }}</p>
                @endif
            </td>
            <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ $holiday->date->format('D d M Y') }}</td>
            <td class="px-4 py-3">
                <span class="badge-{{ $holiday->type === 'religious' ? 'purple' : 'blue' }}">{{ ucfirst($holiday->type) }}</span>
            </td>
            <td class="px-4 py-3 text-center">
                @if($worked > 0)
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">{{ $worked }}</span>
                @else
                    <span class="text-slate-300 text-xs">—</span>
                @endif
            </td>
            <td class="px-4 py-3">
                @if($status === 'approved')
                    <span class="badge-green"><i class="fas fa-check mr-1"></i>Paid</span>
                @elseif($status === 'rejected')
                    <span class="badge-red">Not paid</span>
                @else
                    <span class="badge-yellow">Awaiting decision</span>
                @endif
            </td>
            <td class="px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    @if($clientId)
                    <a href="{{ route('holiday-pay.work', [$holiday, 'client_id' => $clientId]) }}"
                       class="text-xs text-blue-600 hover:underline font-medium">
                        <i class="fas fa-user-check mr-1"></i>Who worked
                    </a>
                    @endif
                    @if($canApprove)
                        <form method="POST" action="{{ route('holiday-pay.decide', $holiday) }}" class="inline">
                            @csrf
                            <input type="hidden" name="client_id" value="{{ $clientId }}">
                            <input type="hidden" name="status" value="approved">
                            <button class="px-2 py-1 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">
                                Approve
                            </button>
                        </form>
                        <form method="POST" action="{{ route('holiday-pay.decide', $holiday) }}" class="inline">
                            @csrf
                            <input type="hidden" name="client_id" value="{{ $clientId }}">
                            <input type="hidden" name="status" value="rejected">
                            <button class="px-2 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold hover:bg-rose-100">
                                Reject
                            </button>
                        </form>
                    @endif
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" class="py-12 text-center text-slate-400">
            No public holidays on the calendar for this period.
        </td></tr>
        @endforelse
    </tbody>
</x-data-table>

<p class="text-xs text-slate-400 mt-4">
    <i class="fas fa-circle-info mr-1"></i>
    Decisions are recorded per client, so one site can pay a holiday while another does not.
    Payroll reads these when the run is processed.
</p>
@endsection
