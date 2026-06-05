@extends("layouts.app")
@section("title","Payslips")
@section("content")
<x-page-header title="{{ $payroll_run->title }} — Payslips">
    <a href="{{ route('payroll.show',$payroll_run) }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    @if(in_array($payroll_run->status, ['md_approved','paid']))
    <form method="POST" action="{{ route('payroll.email-all-payslips', $payroll_run) }}"
          onsubmit="return confirm('Email payslips to all {{ $payslips->total() }} employees? This may take a few minutes.')">
        @csrf
        <button type="submit" class="btn-primary">
            <i class="fas fa-paper-plane"></i> Email All Payslips
        </button>
    </form>
    @endif
</x-page-header>

@if(session('success'))
<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
    <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
</div>
@endif

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-right">Basic</th>
        <th class="table-head px-4 py-3 text-right">Gross</th>
        <th class="table-head px-4 py-3 text-right">Tax</th>
        <th class="table-head px-4 py-3 text-right">Net</th>
        <th class="table-head px-4 py-3 text-center">Actions</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @foreach($payslips as $slip)
        <tr class="table-row">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <img src="{{ $slip->employee->avatar_url }}" class="w-8 h-8 rounded-full">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $slip->employee->full_name }}</p>
                        <p class="text-xs text-slate-400">{{ $slip->employee->emp_number }}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">UGX {{ number_format($slip->basic_salary,2) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">UGX {{ number_format($slip->gross_salary,2) }}</td>
            <td class="px-4 py-3 text-right text-sm text-orange-500">UGX {{ number_format($slip->tax_amount,2) }}</td>
            <td class="px-4 py-3 text-right text-sm font-bold text-green-700">UGX {{ number_format($slip->net_salary,2) }}</td>
            <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-2">
                    {{-- Download PDF --}}
                    <a href="{{ route('payroll.payslip.pdf',[$payroll_run,$slip->employee]) }}"
                       class="btn-secondary text-xs py-1 px-2" title="Download PDF">
                        <i class="fas fa-file-pdf text-red-500"></i> PDF
                    </a>
                    {{-- Email payslip (only after MD approval) --}}
                    @if(in_array($payroll_run->status, ['md_approved','paid']))
                    <form method="POST" action="{{ route('payroll.payslip.email',[$payroll_run,$slip->employee]) }}">
                        @csrf
                        <button type="submit" class="btn-secondary text-xs py-1 px-2" title="Email to {{ $slip->employee->user?->email ?? 'no email' }}">
                            <i class="fas fa-envelope text-blue-500"></i> Email
                        </button>
                    </form>
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
</x-data-table>
{{ $payslips->links() }}
@endsection
