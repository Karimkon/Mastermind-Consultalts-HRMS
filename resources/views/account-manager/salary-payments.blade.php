@extends('layouts.app')
@section('title', 'Salary Payments')
@section('content')

<x-page-header title="Salary Payments" subtitle="Record and track employee salary payments">
    <a href="{{ route('account-manager.salary-payments.create') }}" class="btn-primary text-sm">
        <i class="fas fa-plus mr-1"></i> Record Payment
    </a>
</x-page-header>
<x-alert/>

{{-- Filters --}}
<div class="card p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Client</label>
            <select name="client_id" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">All Clients</option>
                @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Month</label>
            <select name="month" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">All Months</option>
                @foreach(range(1,12) as $m)
                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Year</label>
            <select name="year" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">All Years</option>
                @foreach(range(date('Y'), 2020) as $y)
                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('account-manager.salary-payments') }}" class="btn-secondary text-sm">Clear</a>
    </form>
</div>

{{-- Summary cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ $payments->total() }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Records</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-slate-700">{{ number_format($payments->sum('gross_salary')) }}</p>
        <p class="text-xs text-slate-500 mt-1">Gross (UGX)</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-amber-600">{{ number_format($payments->sum('total_deductions')) }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Deductions (UGX)</p>
    </div>
    <div class="card p-4 text-center border-l-4 border-emerald-500">
        <p class="text-lg font-bold text-emerald-700">{{ number_format($payments->sum('net_salary')) }}</p>
        <p class="text-xs text-slate-500 mt-1">Net Pay (UGX)</p>
    </div>
</div>

{{-- Table --}}
<x-data-table>
    <thead class="bg-slate-50">
        <tr>
            <th class="table-head px-4 py-3 text-left">Employee</th>
            <th class="table-head px-4 py-3 text-left">Client</th>
            <th class="table-head px-4 py-3 text-left">Period</th>
            <th class="table-head px-4 py-3 text-right">Basic</th>
            <th class="table-head px-4 py-3 text-right">Allowances</th>
            <th class="table-head px-4 py-3 text-right">Gross</th>
            <th class="table-head px-4 py-3 text-right">PAYE</th>
            <th class="table-head px-4 py-3 text-right">NSSF</th>
            <th class="table-head px-4 py-3 text-right">Other Ded.</th>
            <th class="table-head px-4 py-3 text-right font-bold text-emerald-700">Net Pay</th>
            <th class="table-head px-4 py-3 text-left">Method</th>
            <th class="table-head px-4 py-3 text-left">Paid On</th>
            <th class="table-head px-4 py-3"></th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($payments as $p)
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3">
                <p class="font-semibold text-slate-800 text-sm">{{ $p->employee?->full_name ?? '—' }}</p>
                <p class="text-xs text-slate-400">{{ $p->employee?->emp_number }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $p->client?->company_name ?? '—' }}</td>
            <td class="px-4 py-3">
                <span class="inline-block bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full">
                    {{ $p->period_label }}
                </span>
            </td>
            <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">{{ number_format($p->basic_salary) }}</td>
            <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">{{ number_format($p->allowances) }}</td>
            <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-slate-800">{{ number_format($p->gross_salary) }}</td>
            <td class="px-4 py-3 text-right font-mono text-sm text-amber-600">{{ number_format($p->paye_tax) }}</td>
            <td class="px-4 py-3 text-right font-mono text-sm text-amber-600">{{ number_format($p->nssf) }}</td>
            <td class="px-4 py-3 text-right font-mono text-sm text-red-500">{{ number_format($p->other_deductions) }}</td>
            <td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-700">{{ number_format($p->net_salary) }}</td>
            <td class="px-4 py-3 text-sm text-slate-500">
                {{ $p->payment_method ? str_replace('_', ' ', ucwords($p->payment_method)) : '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500">
                {{ $p->payment_date?->format('d M Y') ?? '—' }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-right">
                <a href="{{ route('account-manager.salary-payments.edit', $p) }}"
                   class="text-blue-600 hover:text-blue-800 text-xs font-semibold mr-3">Edit</a>
                <form method="POST" action="{{ route('account-manager.salary-payments.destroy', $p) }}" class="inline"
                      onsubmit="return confirm('Delete this payment record?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">Delete</button>
                </form>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="13" class="px-6 py-12 text-center text-slate-400">
                <i class="fas fa-money-bill-wave text-3xl mb-3 block"></i>
                No salary payments recorded yet.
                <a href="{{ route('account-manager.salary-payments.create') }}" class="text-blue-600 font-semibold ml-2">Record first payment</a>
            </td>
        </tr>
        @endforelse
    </tbody>
    @if($payments->count())
    <tfoot class="bg-slate-100 font-bold text-sm">
        <tr>
            <td colspan="3" class="px-4 py-3 text-slate-700">TOTALS (this page)</td>
            <td class="px-4 py-3 text-right font-mono">{{ number_format($payments->sum('basic_salary')) }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ number_format($payments->sum('allowances')) }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ number_format($payments->sum('gross_salary')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-amber-700">{{ number_format($payments->sum('paye_tax')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-amber-700">{{ number_format($payments->sum('nssf')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-red-600">{{ number_format($payments->sum('other_deductions')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-emerald-800">{{ number_format($payments->sum('net_salary')) }}</td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
    @endif
</x-data-table>

<div class="mt-4">{{ $payments->appends(request()->query())->links() }}</div>

@endsection
