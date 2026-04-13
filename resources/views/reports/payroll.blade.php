@extends('layouts.app')
@section('title', 'Payroll Report')
@section('content')
<x-page-header title="Payroll Report" subtitle="Payroll runs and cost summary">
    <a href="{{ route('reports.export', ['type'=>'payroll', 'month'=>request('month'), 'year'=>request('year')]) }}" class="btn-secondary"><i class="fas fa-file-excel mr-1 text-green-600"></i> Export</a>
</x-page-header>

<x-filter-bar :action="route('reports.payroll')">
    <div class="w-36"><select name="month" class="form-input w-full">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected(request('month',date('n'))==$m)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
    <div class="w-24"><input type="number" name="year" class="form-input" value="{{ request('year', date('Y')) }}" min="2020"></div>
</x-filter-bar>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-blue-600">{{ number_format($totals['gross']) }}</p><p class="text-xs text-slate-500 mt-1">Total Gross</p></div>
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-red-600">{{ number_format($totals['deductions']) }}</p><p class="text-xs text-slate-500 mt-1">Deductions</p></div>
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-orange-600">{{ number_format($totals['tax']) }}</p><p class="text-xs text-slate-500 mt-1">Tax</p></div>
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-green-600">{{ number_format($totals['net']) }}</p><p class="text-xs text-slate-500 mt-1">Total Net Pay</p></div>
</div>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Basic</th><th>Gross</th><th>Deductions</th><th>Tax</th><th>Net Pay</th></tr></thead>
    <tbody>
    @forelse($payslips as $slip)
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <img src="{{ $slip->employee->avatar_url }}" class="w-7 h-7 rounded-full">
                <div><p class="text-sm font-medium text-slate-800">{{ $slip->employee->full_name }}</p><p class="text-xs text-slate-500">{{ $slip->employee->emp_number }}</p></div>
            </div>
        </td>
        <td class="px-4 py-3 text-sm">{{ number_format($slip->basic) }}</td>
        <td class="px-4 py-3 text-sm font-medium text-blue-600">{{ number_format($slip->gross) }}</td>
        <td class="px-4 py-3 text-sm text-red-500">{{ number_format($slip->deductions) }}</td>
        <td class="px-4 py-3 text-sm text-orange-500">{{ number_format($slip->tax) }}</td>
        <td class="px-4 py-3 text-sm font-bold text-green-600">{{ number_format($slip->net) }}</td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center py-8 text-slate-400">No payroll data found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $payslips->withQueryString()->links() }}</div>
@endsection