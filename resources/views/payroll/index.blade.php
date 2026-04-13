@extends("layouts.app")
@section("title","Payroll")
@section("content")
<x-page-header title="Payroll Runs" subtitle="Process and manage payroll">
    <a href="{{ route('payroll.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New Payroll Run</a>
    <a href="{{ route('salary.index') }}" class="btn-secondary"><i class="fas fa-money-bill-wave"></i> Salary Setup</a>
    <a href="{{ route('salary.components') }}" class="btn-secondary"><i class="fas fa-list"></i> Components</a>
</x-page-header>
<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Period</th>
        <th class="table-head px-4 py-3 text-center">Employees</th>
        <th class="table-head px-4 py-3 text-right">Gross</th>
        <th class="table-head px-4 py-3 text-right">Net</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
        <th class="table-head px-4 py-3 text-left">Actions</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($runs as $run)
        <tr class="table-row">
            <td class="px-6 py-3"><p class="text-sm font-semibold text-slate-800">{{ $run->title }}</p><p class="text-xs text-slate-500">{{ date("F Y",mktime(0,0,0,$run->month,1,$run->year)) }}</p></td>
            <td class="px-4 py-3 text-center text-sm font-medium text-slate-800">{{ $run->payslips_count }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">UGX {{ number_format($run->payslips->sum("gross_salary"),2) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-green-600">UGX {{ number_format($run->payslips->sum("net_salary"),2) }}</td>
            <td class="px-4 py-3">{!! $run->status_badge !!}</td>
            <td class="px-4 py-3">
                <div class="flex gap-1">
                    <a href="{{ route('payroll.show',$run) }}" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><i class="fas fa-eye text-xs"></i></a>
                    @if(in_array($run->status,["draft","processing"]))<form method="POST" action="{{ route('payroll.process',$run) }}" class="inline">@csrf<button class="p-1.5 text-slate-400 hover:text-green-600 hover:bg-green-50 rounded-lg" title="Process"><i class="fas fa-play text-xs"></i></button></form>@endif
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" class="py-12 text-center text-slate-400">No payroll runs yet. Create one to get started.</td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $runs->links() }}</div>
@endsection
