@extends('layouts.app')
@section('title', $payroll->title)
@section('content')

<x-page-header title="{{ $payroll->title }}"
    subtitle="{{ date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year)) }} · {{ $payroll->client?->company_name ?? 'General Payroll' }}">
    <a href="{{ route('payroll.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>

    @if(!$payroll->isLocked())
        @if(in_array($payroll->status, ['draft','processing']))
        <form method="POST" action="{{ route('payroll.process', $payroll) }}" class="inline">
            @csrf <button class="btn-primary"><i class="fas fa-play mr-1"></i> Process Payroll</button>
        </form>
        @endif

        @if($payroll->status === 'processed')
        <form method="POST" action="{{ route('payroll.approve', $payroll) }}" class="inline">
            @csrf <button class="btn-primary"><i class="fas fa-check-circle mr-1"></i> Approve Payroll</button>
        </form>
        @endif

        @if($payroll->status === 'approved')
        <form method="POST" action="{{ route('payroll.mark-paid', $payroll) }}" class="inline"
              onsubmit="return confirm('Mark as paid? This will automatically lock this payroll run.')">
            @csrf <button class="btn-primary bg-emerald-600 hover:bg-emerald-700"><i class="fas fa-money-bill-wave mr-1"></i> Mark as Paid & Lock</button>
        </form>
        @endif

        @if(in_array($payroll->status, ['processed','approved']))
        <form method="POST" action="{{ route('payroll.lock', $payroll) }}" class="inline"
              onsubmit="return confirm('Lock this payroll run? Only a Super Admin can unlock it later.')">
            @csrf <button class="bg-slate-600 text-white hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-lock mr-1"></i> Lock Run
            </button>
        </form>
        @endif
    @else
        <div class="flex items-center gap-2 px-4 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <i class="fas fa-lock"></i>
            <span>Locked by <strong>{{ $payroll->locker?->name ?? 'System' }}</strong>
            on {{ $payroll->locked_at->format('d M Y H:i') }}</span>
        </div>
        @if(auth()->user()->hasRole('super-admin'))
        <form method="POST" action="{{ route('payroll.unlock', $payroll) }}" class="inline"
              onsubmit="return confirm('Unlock this payroll? Changes will be allowed.')">
            @csrf <button class="bg-red-600 text-white hover:bg-red-700 px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-unlock mr-1"></i> Unlock (Super Admin)
            </button>
        </form>
        @endif
    @endif

    @if(in_array($payroll->status, ['approved','paid']))
    <a href="{{ route('payroll.bank-export', $payroll) }}" class="btn-secondary">
        <i class="fas fa-download mr-1"></i> Bank Export
    </a>
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

{{-- Lock warning banner --}}
@if($payroll->isLocked())
<div class="mb-5 flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">
    <i class="fas fa-lock text-xl"></i>
    <div>
        <p class="font-semibold">This payroll run is locked.</p>
        <p class="text-sm">No further changes can be made.
        @if(!auth()->user()->hasRole('super-admin'))
        Contact a Super Admin to unlock if corrections are needed.
        @endif
        </p>
    </div>
</div>
@endif

{{-- Client info bar --}}
@if($payroll->client)
<div class="mb-5 p-4 bg-indigo-50 border border-indigo-200 rounded-xl flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
            <i class="fas fa-building text-indigo-600"></i>
        </div>
        <div>
            <p class="font-semibold text-indigo-800">{{ $payroll->client->company_name }}</p>
            @if($payroll->client->industry)<p class="text-xs text-indigo-500">{{ $payroll->client->industry }}</p>@endif
        </div>
    </div>
    @if($payroll->client->payment_day)
    <div class="text-sm text-indigo-700">
        <i class="fas fa-calendar-day mr-1"></i>
        Contract pay day: <strong>{{ $payroll->client->payment_day }}{{ ['th','st','nd','rd'][min(3, $payroll->client->payment_day % 10)] }}</strong>
    </div>
    @endif
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
        <p class="text-xl font-bold text-slate-800">UGX {{ number_format($totals['gross'], 0) }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Deductions</p>
        <p class="text-xl font-bold text-red-600">UGX {{ number_format($totals['deductions'], 0) }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">PAYE Tax</p>
        <p class="text-xl font-bold text-orange-600">UGX {{ number_format($totals['tax'], 0) }}</p>
    </div>
    <div class="card p-4 text-center border-2 border-emerald-200">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Total Net Pay</p>
        <p class="text-xl font-bold text-emerald-700">UGX {{ number_format($totals['net'], 0) }}</p>
    </div>
</div>

{{-- Payslips table --}}
<div class="card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Payslips ({{ $totals['count'] }})</h2>
        @if($totals['count'] > 0)
        <a href="{{ route('payroll.payslips', $payroll) }}" class="text-sm text-blue-600 hover:underline">
            View all payslips <i class="fas fa-arrow-right ml-1"></i>
        </a>
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
                <td class="px-4 py-3 text-right text-sm text-slate-600">UGX {{ number_format($slip->basic_salary, 0) }}</td>
                <td class="px-4 py-3 text-right text-sm text-green-600">+{{ number_format($slip->total_allowances, 0) }}</td>
                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">UGX {{ number_format($slip->gross_salary, 0) }}</td>
                <td class="px-4 py-3 text-right text-sm text-red-500">-{{ number_format($slip->total_deductions - $slip->tax_amount, 0) }}</td>
                <td class="px-4 py-3 text-right text-sm text-orange-500">-{{ number_format($slip->tax_amount, 0) }}</td>
                <td class="px-4 py-3 text-right text-sm font-bold text-emerald-700">UGX {{ number_format($slip->net_salary, 0) }}</td>
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
                    @if(!$payroll->isLocked())
                    <p class="text-slate-400 text-xs mt-1">Click "Process Payroll" to calculate all payslips.</p>
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
