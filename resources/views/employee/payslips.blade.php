@extends("layouts.app")
@section("title", "My Payslips")
@section("content")
<x-page-header title="My Payslips" subtitle="Your released payslips, newest first"/>

<x-alert/>

@if($payslips->isEmpty())
    <div class="card p-10 text-center">
        <i class="fas fa-file-invoice-dollar text-4xl text-slate-300 mb-3"></i>
        <p class="text-slate-600 font-medium">No payslips yet</p>
        {{-- Said plainly, because "nothing here" and "your pay is still being
             approved" are very different things to the person reading it. --}}
        <p class="text-slate-500 text-sm mt-1">
            A payslip appears here once the payroll run it belongs to has been
            approved by the Managing Director.
        </p>
    </div>
@else
    @php
        $paid = $payslips->where('payment_status', 'paid');
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card p-4 border-l-4 border-blue-500">
            <p class="text-lg font-bold text-slate-800">{{ $payslips->count() }}</p>
            <p class="text-xs text-slate-500 mt-1">Payslips</p>
        </div>
        <div class="card p-4 border-l-4 border-emerald-500">
            <p class="text-lg font-bold text-emerald-700">UGX {{ number_format((float) $paid->sum('net_salary')) }}</p>
            <p class="text-xs text-slate-500 mt-1">Total paid to date</p>
        </div>
        <div class="card p-4 border-l-4 border-slate-400">
            <p class="text-lg font-bold text-slate-800">
                UGX {{ number_format((float) ($payslips->first()->net_salary ?? 0)) }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Most recent net pay</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold">Period</th>
                        <th class="text-right px-5 py-3 font-semibold">Gross</th>
                        <th class="text-right px-5 py-3 font-semibold">Deductions</th>
                        <th class="text-right px-5 py-3 font-semibold">Net pay</th>
                        <th class="text-left px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($payslips as $slip)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <span class="font-medium text-slate-800">
                                {{ \Carbon\Carbon::create($slip->payrollRun->year, $slip->payrollRun->month, 1)->format('F Y') }}
                            </span>
                            <span class="block text-xs text-slate-400">{{ $slip->payrollRun->title }}</span>
                        </td>
                        <td class="px-5 py-3 text-right text-slate-700">{{ number_format((float) $slip->gross_salary) }}</td>
                        <td class="px-5 py-3 text-right text-slate-700">{{ number_format((float) $slip->total_deductions) }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ number_format((float) $slip->net_salary) }}</td>
                        <td class="px-5 py-3">
                            @if($slip->payment_status === 'paid')
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">Paid</span>
                            @elseif($slip->payment_status === 'withheld')
                                {{-- Somebody who was not paid has to be able to see that, and why. --}}
                                <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-semibold">Withheld</span>
                                @if($slip->withheld_reason)
                                    <span class="block text-xs text-red-600 mt-1">{{ $slip->withheld_reason }}</span>
                                @endif
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">Awaiting payment</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('payroll.payslip.pdf', [$slip->payroll_run_id, $slip->employee_id]) }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100">
                                <i class="fas fa-download"></i> PDF
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
