@extends('layouts.app')
@section('title', 'Payment Readiness · ' . $payroll->title)
@section('content')

@php
    $blocked  = $rows->filter(fn($r) => $r['issue'] !== null);
    $zeroPay  = $rows->filter(fn($r) => $r['zero_pay']);
    $ready    = $rows->filter(fn($r) => $r['issue'] === null && !$r['zero_pay']);
    $channels = ['bank' => 'Bank Transfer (EFT)', 'mtn' => 'MTN Mobile Money', 'airtel' => 'Airtel Mobile Money',
                 'cash' => 'Cash', 'cheque' => 'Cheque', 'unassigned' => 'Not determined'];
@endphp

<x-page-header title="Payment Readiness"
    subtitle="{{ $payroll->title }} · {{ date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year)) }}">
    <a href="{{ route('payroll.show', $payroll) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to Payroll</a>
    <a href="{{ route('payroll.bank-export', $payroll) }}" class="btn-secondary">
        <i class="fas fa-file-csv text-emerald-600 mr-1"></i> Download Full Schedule
    </a>
</x-page-header>

{{-- ── Summary ── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Payslips</p>
        <p class="text-2xl font-bold text-slate-800">{{ $rows->count() }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Ready to Pay</p>
        <p class="text-2xl font-bold text-emerald-600">{{ $ready->count() }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Missing Details</p>
        <p class="text-2xl font-bold {{ $blocked->count() ? 'text-red-600' : 'text-slate-800' }}">{{ $blocked->count() }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">Zero Net Pay</p>
        <p class="text-2xl font-bold {{ $zeroPay->count() ? 'text-amber-600' : 'text-slate-800' }}">{{ $zeroPay->count() }}</p>
    </div>
</div>

@if(blank($kcbAccount))
<div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800">
    <p class="font-semibold mb-1"><i class="fas fa-building-columns mr-1"></i> The KCB debit account number is not configured.</p>
    <p>Every KCB file starts with the account the money is drawn from. While it is blank the bank will reject the upload.
       Set it under <a href="{{ route('admin.settings.index') }}" class="underline font-medium">Admin → Settings</a>
       as <em>kcb_account_number</em>.</p>
</div>
@endif

@if($blocked->count())
<div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800">
    <p class="font-semibold mb-1"><i class="fas fa-triangle-exclamation mr-1"></i> {{ $blocked->count() }} employee(s) will not appear in the bank files.</p>
    <p>Fix the details below before downloading the KCB files, otherwise these people simply will not be paid and nothing will flag it.</p>
</div>
@endif

@if($zeroPay->count())
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
    <p class="font-semibold mb-1"><i class="fas fa-circle-info mr-1"></i> {{ $zeroPay->count() }} payslip(s) have a net pay of zero.</p>
    <p>This normally means days worked were not uploaded for them. Check the Manual Days upload before approving.</p>
</div>
@endif

{{-- ── Per-channel breakdown ── --}}
<div class="card mb-6 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800">Breakdown by Payment File</h2>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
            <tr>
                <th class="text-left px-4 py-2.5">Payment File</th>
                <th class="text-right px-4 py-2.5">Employees</th>
                <th class="text-right px-4 py-2.5">In File</th>
                <th class="text-right px-4 py-2.5">Blocked</th>
                <th class="text-right px-4 py-2.5">Zero Pay</th>
                <th class="text-right px-4 py-2.5">Amount (UGX)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @foreach($byChannel as $key => $stat)
            <tr>
                <td class="px-4 py-2.5 font-medium text-slate-700">{{ $channels[$key] ?? ucfirst($key) }}</td>
                <td class="px-4 py-2.5 text-right">{{ $stat['count'] }}</td>
                <td class="px-4 py-2.5 text-right text-emerald-700 font-semibold">{{ $stat['ready'] }}</td>
                <td class="px-4 py-2.5 text-right {{ $stat['blocked'] ? 'text-red-600 font-semibold' : 'text-slate-400' }}">{{ $stat['blocked'] }}</td>
                <td class="px-4 py-2.5 text-right {{ $stat['zero_pay'] ? 'text-amber-600' : 'text-slate-400' }}">{{ $stat['zero_pay'] }}</td>
                <td class="px-4 py-2.5 text-right font-semibold">{{ number_format($stat['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

{{-- ── Detail ── --}}
<div class="card overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Employee Detail</h2>
        <p class="text-xs text-slate-500">Problems listed first</p>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
            <tr>
                <th class="text-left px-4 py-2.5">Emp No</th>
                <th class="text-left px-4 py-2.5">Name</th>
                <th class="text-left px-4 py-2.5">Payment Mode</th>
                <th class="text-left px-4 py-2.5">Destination</th>
                <th class="text-right px-4 py-2.5">Net Pay</th>
                <th class="text-left px-4 py-2.5">Status</th>
                <th class="px-4 py-2.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @foreach($rows as $r)
            @php $emp = $r['employee']; @endphp
            <tr class="{{ $r['issue'] ? 'bg-red-50/50' : '' }}">
                <td class="px-4 py-2.5 text-slate-500">{{ $emp->emp_number }}</td>
                <td class="px-4 py-2.5 font-medium text-slate-800">{{ $emp->full_name }}</td>
                <td class="px-4 py-2.5">{{ $channels[$r['channel']] ?? ucfirst($r['channel']) }}</td>
                <td class="px-4 py-2.5 text-slate-600">
                    @if(in_array($r['channel'], ['mtn','airtel']))
                        {{ $emp->mobile_money_number ?: '—' }}
                    @elseif($r['channel'] === 'bank')
                        {{ $emp->bank_name ?: '—' }} · {{ $emp->bank_account ?: '—' }}
                    @else
                        —
                    @endif
                </td>
                <td class="px-4 py-2.5 text-right font-semibold {{ $r['zero_pay'] ? 'text-amber-600' : '' }}">
                    {{ number_format($r['slip']->net_salary) }}
                </td>
                <td class="px-4 py-2.5">
                    @if($r['issue'])
                        <span class="badge-red">{{ $r['issue'] }}</span>
                    @elseif($r['zero_pay'])
                        <span class="badge-yellow">Zero net pay</span>
                    @else
                        <span class="badge-green">Ready</span>
                    @endif
                </td>
                <td class="px-4 py-2.5 text-right">
                    {{-- employees.edit is limited to super-admin/hr-admin/manager;
                         payroll officers see the problem but cannot edit the record. --}}
                    @if($r['issue'] && auth()->user()->hasAnyRole(['super-admin','hr-admin','manager']))
                    <a href="{{ route('employees.edit', $emp) }}#payment"
                       class="text-indigo-600 hover:underline text-xs font-medium">Fix</a>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

@endsection
