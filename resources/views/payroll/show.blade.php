@extends('layouts.app')
@section('title', $payroll->title)
@section('content')

<x-page-header title="{{ $payroll->title }}"
    subtitle="{{ date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year)) }} · {{ $payroll->client?->company_name ?? 'General Payroll' }}">
    <a href="{{ route('payroll.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>

    {{-- Stage 1: AM processes --}}
    @if(!$payroll->isLocked() && in_array($payroll->status, ['draft','processing']))
    <form method="POST" action="{{ route('payroll.process', $payroll) }}" class="inline">
        @csrf <button class="btn-primary"><i class="fas fa-play mr-1"></i> Run &amp; Submit to HR</button>
    </form>
    @endif

    {{-- Stage 2: HR approves --}}
    @if(!$payroll->isLocked() && $payroll->status === 'processed')
    @role('super-admin|hr-admin')
    <form method="POST" action="{{ route('payroll.hr-approve', $payroll) }}" class="inline">
        @csrf <button class="btn-primary"><i class="fas fa-check mr-1"></i> HR Approve → Finance</button>
    </form>
    @endrole
    @endif

    {{-- Stage 3: Finance approves --}}
    @if(!$payroll->isLocked() && $payroll->status === 'hr_approved')
    @role('super-admin|payroll-officer')
    <form method="POST" action="{{ route('payroll.finance-approve', $payroll) }}" class="inline">
        @csrf <button class="btn-primary" style="background:#7c3aed;"><i class="fas fa-check-double mr-1"></i> Finance Approve → MD</button>
    </form>
    @endrole
    @endif

    {{-- Stage 4: MD final approval — auto-locks --}}
    @if(!$payroll->isLocked() && $payroll->status === 'finance_approved')
    @role('super-admin')
    <form method="POST" action="{{ route('payroll.approve', $payroll) }}" class="inline"
          onsubmit="return confirm('Give MD final approval? This will lock the payroll immediately.')">
        @csrf <button class="btn-primary" style="background:#059669;"><i class="fas fa-stamp mr-1"></i> MD Final Approval (Locks)</button>
    </form>
    @endrole
    @endif

    {{-- After MD approval: mark paid --}}
    @if(in_array($payroll->status, ['md_approved','approved']) && $payroll->isLocked())
    @role('super-admin|payroll-officer')
    <form method="POST" action="{{ route('payroll.mark-paid', $payroll) }}" class="inline"
          onsubmit="return confirm('Mark as paid? Employees will be notified.')">
        @csrf <button class="btn-primary" style="background:#059669;"><i class="fas fa-money-bill-wave mr-1"></i> Mark as Paid</button>
    </form>
    @endrole
    @endif

    {{-- Downloads: available after MD approval --}}
    @if(in_array($payroll->status, ['md_approved','approved','paid']))
    @can('reports.export')
    <a href="{{ route('payroll.export-pdf', $payroll) }}" class="btn-secondary">
        <i class="fas fa-file-pdf text-red-500 mr-1"></i> Summary PDF
    </a>
    <a href="{{ route('payroll.export-excel', $payroll) }}" class="btn-secondary">
        <i class="fas fa-file-excel text-green-600 mr-1"></i> Export Excel
    </a>
    {{-- KCB Bulk Payment Files --}}
    <div x-data="{ open: false }" class="relative">
        <button @click="open = !open" type="button" class="btn-secondary flex items-center gap-1">
            <i class="fas fa-landmark text-blue-600 mr-1"></i> KCB Payment Files
            <i class="fas fa-chevron-down text-xs ml-1" :class="open ? 'rotate-180' : ''" style="transition:transform .2s"></i>
        </button>
        <div x-show="open" @click.outside="open=false" x-cloak
             class="absolute right-0 top-full mt-1 w-56 bg-white border border-slate-200 rounded-xl shadow-lg z-50 py-1">
            <a href="{{ route('payroll.kcb-eft', $payroll) }}"
               class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-university text-blue-600 text-xs"></i>
                </span>
                <div>
                    <p class="font-medium">EFT Bank Transfer</p>
                    <p class="text-xs text-slate-400">Bank account employees</p>
                </div>
            </a>
            <a href="{{ route('payroll.kcb-mtn', $payroll) }}"
               class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                <span class="w-7 h-7 rounded-lg bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-mobile-alt text-yellow-600 text-xs"></i>
                </span>
                <div>
                    <p class="font-medium">MTN Mobile Money</p>
                    <p class="text-xs text-slate-400">MTN payment employees</p>
                </div>
            </a>
            <a href="{{ route('payroll.kcb-airtel', $payroll) }}"
               class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center">
                    <i class="fas fa-mobile-alt text-red-600 text-xs"></i>
                </span>
                <div>
                    <p class="font-medium">Airtel Mobile Money</p>
                    <p class="text-xs text-slate-400">Airtel payment employees</p>
                </div>
            </a>
        </div>
    </div>
    @endcan
    @endif

    {{-- Locked indicator --}}
    @if($payroll->isLocked())
    <div class="flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
        <i class="fas fa-lock"></i>
        <span>Locked by <strong>{{ $payroll->locker?->name ?? 'System' }}</strong></span>
    </div>
    @role('super-admin')
    <form method="POST" action="{{ route('payroll.unlock', $payroll) }}" class="inline"
          onsubmit="return confirm('Unlock this payroll? Changes will be allowed.')">
        @csrf <button class="bg-red-600 text-white hover:bg-red-700 px-3 py-2 rounded-lg text-xs font-medium">
            <i class="fas fa-unlock mr-1"></i> Unlock
        </button>
    </form>
    @endrole
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

{{-- Approval Workflow Progress --}}
@php
    $stage = $payroll->workflowStage();
    $stages = [
        ['label'=>'AM Processes','sub'=>$payroll->processor?->name ?? 'Pending','date'=>$payroll->processed_at,'icon'=>'fa-cogs'],
        ['label'=>'HR Approval','sub'=>$payroll->hrApprover?->name ?? 'Pending','date'=>$payroll->hr_approved_at,'icon'=>'fa-user-check'],
        ['label'=>'Finance Approval','sub'=>$payroll->financeApprover?->name ?? 'Pending','date'=>$payroll->finance_approved_at,'icon'=>'fa-calculator'],
        ['label'=>'MD Approval','sub'=>$payroll->mdApprover?->name ?? 'Pending','date'=>$payroll->md_approved_at,'icon'=>'fa-stamp'],
        ['label'=>'Paid','sub'=>$payroll->status === 'paid' ? 'Completed' : 'Pending','date'=>$payroll->paid_at,'icon'=>'fa-check-circle'],
    ];
@endphp
<div class="card p-5 mb-5">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Approval Workflow</h3>
    <div class="flex items-start justify-between relative">
        <div class="absolute top-5 left-0 right-0 h-0.5 bg-slate-200 z-0" style="margin:0 10%"></div>
        @foreach($stages as $i => $s)
        @php $done = $stage > $i; $current = $stage === $i; @endphp
        <div class="flex flex-col items-center z-10 flex-1">
            <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 mb-2
                {{ $done ? 'bg-green-500 border-green-500 text-white' : ($current ? 'bg-blue-500 border-blue-500 text-white animate-pulse' : 'bg-white border-slate-200 text-slate-400') }}">
                <i class="fas {{ $s['icon'] }} text-sm"></i>
            </div>
            <p class="text-xs font-semibold text-slate-700 text-center">{{ $s['label'] }}</p>
            <p class="text-xs text-slate-400 text-center">{{ $s['sub'] }}</p>
            @if($s['date'])<p class="text-xs text-green-600 text-center">{{ \Carbon\Carbon::parse($s['date'])->format('d M') }}</p>@endif
        </div>
        @endforeach
    </div>
</div>

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
