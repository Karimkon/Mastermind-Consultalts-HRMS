@extends("layouts.app")
@section("title","Payslips")
@section("content")
<x-page-header title="{{ $payroll_run->title }} — Payslips">
    <a href="{{ route('payroll.show',$payroll_run) }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-right">Basic</th>
        <th class="table-head px-4 py-3 text-right">Gross</th>
        <th class="table-head px-4 py-3 text-right">Tax</th>
        <th class="table-head px-4 py-3 text-right">Net</th>
        <th class="table-head px-4 py-3 text-left">PDF</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @foreach($payslips as $slip)
        <tr class="table-row">
            <td class="px-6 py-3"><div class="flex items-center gap-3"><img src="{{ $slip->employee->avatar_url }}" class="w-8 h-8 rounded-full"><p class="text-sm font-medium text-slate-800">{{ $slip->employee->full_name }}</p></div></td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">UGX {{ number_format($slip->basic_salary,2) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">UGX {{ number_format($slip->gross_salary,2) }}</td>
            <td class="px-4 py-3 text-right text-sm text-orange-500">UGX {{ number_format($slip->tax_amount,2) }}</td>
            <td class="px-4 py-3 text-right text-sm font-bold text-green-700">UGX {{ number_format($slip->net_salary,2) }}</td>
            <td class="px-4 py-3"><a href="{{ route('payroll.payslip.pdf',[$payroll_run,$slip->employee]) }}" class="btn-secondary text-xs py-1 px-2"><i class="fas fa-file-pdf text-red-500"></i> PDF</a></td>
        </tr>
        @endforeach
    </tbody>
</x-data-table>
@endsection
