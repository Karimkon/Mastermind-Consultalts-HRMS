@extends("layouts.app")
@section("title", $activeClient ? $activeClient->company_name . ' — Payroll' : 'Payroll')
@section("content")

<x-page-header
    :title="$activeClient ? $activeClient->company_name . ' — Payroll' : 'Payroll'"
    subtitle="View payroll runs and payslips for your employees">
    <a href="{{ route('account-manager.dashboard') }}" class="btn-secondary text-sm">
        <i class="fas fa-arrow-left mr-1"></i> Dashboard
    </a>
</x-page-header>
<x-alert/>

{{-- Company filter tabs --}}
<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('account-manager.payroll') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium border transition
              {{ !$clientId ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
        <i class="fas fa-globe-africa mr-1"></i>All Companies
    </a>
    @foreach($clients as $c)
    <a href="{{ route('account-manager.payroll', ['client_id' => $c->id]) }}"
       class="px-4 py-2 rounded-lg text-sm font-medium border transition flex items-center gap-2
              {{ $clientId === $c->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
        <span class="w-2 h-2 rounded-full {{ $c->status === 'active' ? 'bg-emerald-400' : 'bg-slate-300' }}"></span>
        {{ $c->company_name }}
        @if($c->payment_day)
            <span class="text-xs opacity-70">· Pay day {{ $c->payment_day }}</span>
        @endif
    </a>
    @endforeach
</div>

{{-- Summary cards --}}
@if($runs->count())
@php
    $totalGross = $runs->sum(fn($r) => $r->payslips()->sum('gross_salary'));
    $totalNet   = $runs->sum(fn($r) => $r->payslips()->sum('net_salary'));
    $totalEmps  = $runs->sum(fn($r) => $r->payslips()->count());
@endphp
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ $runs->total() }}</p>
        <p class="text-xs text-slate-500 mt-1">Payroll Runs</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-slate-800">{{ $totalEmps }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Employees</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-green-600">{{ number_format($totalGross) }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Gross (UGX)</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-emerald-700">{{ number_format($totalNet) }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Net (UGX)</p>
    </div>
</div>
@endif

<x-data-table>
    <thead class="bg-slate-50">
        <tr>
            <th class="table-head px-6 py-3 text-left">Period</th>
            @if(!$clientId)<th class="table-head px-4 py-3 text-left">Company</th>@endif
            <th class="table-head px-4 py-3 text-center">Employees</th>
            <th class="table-head px-4 py-3 text-right">Gross (UGX)</th>
            <th class="table-head px-4 py-3 text-right">Net (UGX)</th>
            <th class="table-head px-4 py-3 text-center">Payment Date</th>
            <th class="table-head px-4 py-3 text-center">Status</th>
            <th class="table-head px-4 py-3 text-center">Action</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
        @forelse($runs as $run)
        @php
            $gross = $run->payslips()->sum('gross_salary');
            $net   = $run->payslips()->sum('net_salary');
            $count = $run->payslips()->count();
        @endphp
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-3">
                <p class="font-semibold text-slate-800">{{ $run->title }}</p>
                <p class="text-xs text-slate-400">Created {{ $run->created_at?->format('d M Y') }}</p>
            </td>
            @if(!$clientId)
            <td class="px-4 py-3 text-sm text-slate-600">{{ $run->client?->company_name ?? '—' }}</td>
            @endif
            <td class="px-4 py-3 text-center text-slate-700 font-medium">{{ $count }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ number_format($gross) }}</td>
            <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-700">{{ number_format($net) }}</td>
            <td class="px-4 py-3 text-center text-slate-600 text-sm">
                {{ $run->payment_date?->format('d M Y') ?? '—' }}
            </td>
            <td class="px-4 py-3 text-center">
                @if($run->isLocked())
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                        <i class="fas fa-lock text-xs"></i> Locked
                    </span>
                @else
                    @switch($run->status)
                        @case('draft')
                            <span class="badge-gray text-xs">Draft</span>
                            @break
                        @case('processing')
                            <span class="badge-yellow text-xs">Processing</span>
                            @break
                        @case('processed')
                            <span class="badge-blue text-xs">Processed</span>
                            @break
                        @case('approved')
                            <span class="badge-green text-xs">Approved</span>
                            @break
                        @case('paid')
                            <span class="badge-green text-xs"><i class="fas fa-check mr-1"></i>Paid</span>
                            @break
                    @endswitch
                @endif
            </td>
            <td class="px-4 py-3 text-center">
                @if($count > 0)
                <a href="{{ route('account-manager.payroll.show', $run) }}"
                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fas fa-eye mr-1"></i> View
                </a>
                @else
                <span class="text-slate-300 text-sm">—</span>
                @endif
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="8" class="px-6 py-16 text-center text-slate-400">
                <i class="fas fa-file-invoice-dollar text-4xl mb-3 block"></i>
                No payroll runs found for your clients yet.
                <br><span class="text-sm">Payroll is processed by HR Admin — check back after the payment date.</span>
            </td>
        </tr>
        @endforelse
    </tbody>
</x-data-table>

@if($runs->hasPages())
<div class="mt-4">{{ $runs->links() }}</div>
@endif

@endsection
