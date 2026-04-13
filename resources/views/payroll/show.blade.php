@extends('layouts.app')
@section('title', $payroll->title)
@section('content')

<x-page-header title="{{ $payroll->title }}" subtitle="{{ date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year)) }} · {{ ucfirst($payroll->status) }}">
    <a href="{{ route('payroll.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>

    @if(in_array($payroll->status, ['draft','processing']))
    <form method="POST" action="{{ route('payroll.process', $payroll) }}" class="inline">
        @csrf
        <button class="btn-primary"><i class="fas fa-play"></i> Process Payroll</button>
    </form>
    @endif

    @if($payroll->status === 'processed')
    <form method="POST" action="{{ route('payroll.approve', $payroll) }}" class="inline">
        @csrf
        <button class="btn-primary"><i class="fas fa-check-circle"></i> Approve Payroll</button>
    </form>
    @endif

    @if($payroll->status === 'approved')
    <form method="POST" action="{{ route('payroll.mark-paid', $payroll) }}" class="inline">
        @csrf
        <button class="btn-primary bg-green-600 hover:bg-green-700"><i class="fas fa-money-bill-wave"></i> Mark as Paid</button>
    </form>
    @endif
</x-page-header>

@if(session('success'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
</div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Employees</p>
        <p class="text-2xl font-bold text-slate-800">{{ $totals['count'] }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Total Gross</p>
        <p class="text-xl font-bold text-slate-800">UGX {{ number_format($totals['gross'], 2) }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Total Deductions</p>
        <p class="text-xl font-bold text-red-600">UGX {{ number_format($totals['deductions'], 2) }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">PAYE Tax</p>
        <p class="text-xl font-bold text-orange-600">UGX {{ number_format($totals['tax'], 2) }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Total Net Pay</p>
        <p class="text-xl font-bold text-green-600">UGX {{ number_format($totals['net'], 2) }}</p>
    </div>
</div>

{{-- Payslips table --}}
<div class="card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Payslips ({{ $totals['count'] }})</h2>
        @if($totals['count'] > 0)
        <span class="text-xs text-slate-500">Click PDF to download individual payslip</span>
        @endif
    </div>
    <table class="w-full">
        <thead class="bg-slate-50">
            <tr>
                <th class="table-head px-6 py-3 text-left">Employee</th>
                <th class="table-head px-4 py-3 text-right">Basic</th>
                <th class="table-head px-4 py-3 text-right">Allowances</th>
                <th class="table-head px-4 py-3 text-right">Gross</th>
                <th class="table-head px-4 py-3 text-right">Deductions</th>
                <th class="table-head px-4 py-3 text-right">PAYE</th>
                <th class="table-head px-4 py-3 text-right">Net Pay</th>
                <th class="table-head px-4 py-3 text-center">Days</th>
                <th class="table-head px-4 py-3 text-left">PDF</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($payroll->payslips as $slip)
            <tr class="table-row">
                <td class="px-6 py-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ $slip->employee->avatar_url }}" class="w-8 h-8 rounded-full object-cover">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $slip->employee->full_name }}</p>
                            <p class="text-xs text-slate-400">{{ $slip->employee->department?->name ?? '—' }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-right text-sm text-slate-600">UGX {{ number_format($slip->basic_salary, 2) }}</td>
                <td class="px-4 py-3 text-right text-sm text-green-600">+UGX {{ number_format($slip->total_allowances, 2) }}</td>
                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">UGX {{ number_format($slip->gross_salary, 2) }}</td>
                <td class="px-4 py-3 text-right text-sm text-red-500">-UGX {{ number_format($slip->total_deductions - $slip->tax_amount, 2) }}</td>
                <td class="px-4 py-3 text-right text-sm text-orange-500">-UGX {{ number_format($slip->tax_amount, 2) }}</td>
                <td class="px-4 py-3 text-right text-sm font-bold text-green-700">UGX {{ number_format($slip->net_salary, 2) }}</td>
                <td class="px-4 py-3 text-center text-xs text-slate-500">
                    <span class="text-green-600 font-medium">{{ $slip->worked_days }}P</span> /
                    <span class="text-red-400">{{ $slip->absent_days }}A</span>
                </td>
                <td class="px-4 py-3">
                    <a href="{{ route('payroll.payslip.pdf', [$payroll, $slip->employee]) }}"
                       class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800 font-medium">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="py-12 text-center">
                    <i class="fas fa-calculator text-3xl text-slate-300 mb-3 block"></i>
                    <p class="text-slate-500 text-sm">No payslips yet.</p>
                    <p class="text-slate-400 text-xs mt-1">Click "Process Payroll" to calculate all payslips.</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
