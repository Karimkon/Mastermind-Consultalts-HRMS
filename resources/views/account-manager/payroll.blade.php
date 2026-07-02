@extends("layouts.app")
@section("title", $activeClient ? $activeClient->company_name . ' — Payroll' : 'Payroll Runs')
@section("content")

<x-page-header
    :title="$activeClient ? $activeClient->company_name . ' — Payroll' : 'Payroll Runs'"
    subtitle="Manage payroll for all your client companies">
    <button onclick="document.getElementById('new-run-modal').classList.remove('hidden')"
            class="btn-primary text-sm">
        <i class="fas fa-plus mr-1"></i> New Run
    </button>
    <a href="{{ route('account-manager.dashboard') }}" class="btn-secondary text-sm">
        <i class="fas fa-arrow-left mr-1"></i> Dashboard
    </a>
</x-page-header>

{{-- New Run Modal --}}
<div id="new-run-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-base">
                <i class="fas fa-plus-circle mr-2 text-blue-500"></i>Create New Payroll Run
            </h3>
            <button onclick="document.getElementById('new-run-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('account-manager.payroll.create-run') }}">
            @csrf
            <div class="p-5 space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Creates a Draft run. You will then upload manual days and click "Run & Submit to HR".
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Client Company *</label>
                    <select name="client_id" class="form-input" required>
                        <option value="">— Select Client —</option>
                        @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->company_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Month *</label>
                        <select name="month" class="form-input" required>
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($m == date('n'))>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Year *</label>
                        <select name="year" class="form-input" required>
                            @for($y = date('Y'); $y >= 2024; $y--)
                            <option value="{{ $y }}" @selected($y == date('Y'))>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Employment Type *</label>
                    <select name="employment_type" class="form-input" required>
                        <option value="casual">Casual (Daily / Hourly)</option>
                        <option value="contract">Contract (Monthly Salary)</option>
                        <option value="mixed">Mixed (Both)</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 justify-end p-5 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('new-run-modal').classList.add('hidden')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-plus mr-1"></i> Create Run</button>
            </div>
        </form>
    </div>
</div>
<x-alert/>

{{-- ── Stats row ─────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ $stats['total_runs'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Runs</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-amber-600">{{ $stats['casual_runs'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Casual Payrolls</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-indigo-600">{{ $stats['contract_runs'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Contract Payrolls</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-orange-500">{{ $stats['pending_runs'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Pending Approval</p>
    </div>
</div>

{{-- ── Filters ───────────────────────────────────────────────────── --}}
<form method="GET" class="flex flex-wrap gap-2 mb-5 items-end">

    {{-- Company tabs --}}
    <div class="flex flex-wrap gap-2 flex-1">
        <a href="{{ route('account-manager.payroll', array_merge(request()->except('client_id'), [])) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium border transition
                  {{ !$clientId ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            <i class="fas fa-globe-africa mr-1"></i>All Companies
        </a>
        @foreach($clients as $c)
        <a href="{{ route('account-manager.payroll', array_merge(request()->except('client_id'), ['client_id' => $c->id])) }}"
           class="px-4 py-2 rounded-lg text-sm font-medium border transition flex items-center gap-2
                  {{ $clientId === $c->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            <span class="w-2 h-2 rounded-full {{ $c->status === 'active' ? 'bg-emerald-400' : 'bg-slate-300' }}"></span>
            {{ $c->company_name }}
        </a>
        @endforeach
    </div>

    {{-- Employment type --}}
    <select name="emp_type" onchange="this.form.submit()"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-300 outline-none">
        <option value="all"      {{ ($empType ?? 'all') === 'all'      ? 'selected' : '' }}>All Types</option>
        <option value="casual"   {{ ($empType ?? '') === 'casual'      ? 'selected' : '' }}>Casual (Hourly/Daily)</option>
        <option value="contract" {{ ($empType ?? '') === 'contract'    ? 'selected' : '' }}>Contract (Monthly)</option>
    </select>

    {{-- Status filter --}}
    <select name="status_f" onchange="this.form.submit()"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-300 outline-none">
        <option value=""             {{ !$statusF ? 'selected' : '' }}>All Statuses</option>
        <option value="draft"        {{ $statusF === 'draft'            ? 'selected' : '' }}>Draft (Not started)</option>
        <option value="processed"    {{ $statusF === 'processed'        ? 'selected' : '' }}>Awaiting HR</option>
        <option value="hr_approved"  {{ $statusF === 'hr_approved'      ? 'selected' : '' }}>Awaiting Finance</option>
        <option value="finance_approved" {{ $statusF === 'finance_approved' ? 'selected' : '' }}>Awaiting MD</option>
        <option value="md_approved"  {{ $statusF === 'md_approved'      ? 'selected' : '' }}>MD Approved</option>
        <option value="paid"         {{ $statusF === 'paid'             ? 'selected' : '' }}>Paid</option>
    </select>

    @if($clientId)<input type="hidden" name="client_id" value="{{ $clientId }}">@endif
    @if(($empType ?? 'all') !== 'all' || $statusF)
    <a href="{{ route('account-manager.payroll', $clientId ? ['client_id' => $clientId] : []) }}"
       class="px-3 py-2 rounded-lg border border-red-200 text-red-600 text-sm hover:bg-red-50">
        <i class="fas fa-times mr-1"></i>Clear filters
    </a>
    @endif
</form>

{{-- ── Payroll runs table ────────────────────────────────────────── --}}
<div class="card overflow-hidden">
    <table class="w-full">
        <thead class="bg-slate-50">
            <tr>
                <th class="table-head px-6 py-3 text-left">Period & Title</th>
                <th class="table-head px-4 py-3 text-left">Company</th>
                <th class="table-head px-4 py-3 text-center">Type</th>
                <th class="table-head px-4 py-3 text-center">Employees</th>
                <th class="table-head px-4 py-3 text-right">Gross (UGX)</th>
                <th class="table-head px-4 py-3 text-right">Net (UGX)</th>
                <th class="table-head px-4 py-3 text-center">Status</th>
                <th class="table-head px-4 py-3 text-center">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($runs as $run)
            @php
                $paidCount  = $run->payslips()->where('gross_salary', '>', 0)->count();
                $grossTotal = $run->payslips()->sum('gross_salary');
                $netTotal   = $run->payslips()->sum('net_salary');
                $empType    = $run->employment_type ?? 'all';
            @endphp
            <tr class="hover:bg-slate-50 transition-colors cursor-pointer"
                onclick="window.location='{{ route('account-manager.payroll.show', $run) }}'">

                <td class="px-6 py-3">
                    <a href="{{ route('account-manager.payroll.show', $run) }}"
                       class="font-semibold text-blue-700 hover:underline text-sm">{{ $run->title }}</a>
                    <p class="text-xs text-slate-400">
                        {{ date('F Y', mktime(0,0,0,$run->month,1,$run->year)) }}
                        · Created {{ $run->created_at?->format('d M Y') }}
                    </p>
                </td>

                <td class="px-4 py-3 text-sm text-slate-600">
                    {{ $run->client?->company_name ?? 'General' }}
                </td>

                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                    @if($empType === 'casual')
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                        <i class="fas fa-user-clock text-xs"></i> Casual
                    </span>
                    @elseif($empType === 'contract')
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
                        <i class="fas fa-file-contract text-xs"></i> Contract
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                        <i class="fas fa-users text-xs"></i> Mixed
                    </span>
                    @endif
                    @if($run->hours_based)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-orange-100 text-orange-700 ml-1">
                        <i class="fas fa-clock text-xs"></i> Hourly
                    </span>
                    @endif
                </td>

                <td class="px-4 py-3 text-center text-slate-700 font-medium text-sm">
                    {{ $paidCount ?: '—' }}
                </td>

                <td class="px-4 py-3 text-right font-mono text-sm text-slate-700">
                    {{ $grossTotal > 0 ? number_format($grossTotal) : '—' }}
                </td>

                <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-emerald-700">
                    {{ $netTotal > 0 ? number_format($netTotal) : '—' }}
                </td>

                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                    @if($run->isLocked())
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                        <i class="fas fa-lock text-xs"></i> Locked
                    </span>
                    @else
                    @switch($run->status)
                        @case('draft')
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">Draft</span>
                            @break
                        @case('processed')
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                <i class="fas fa-user-check text-xs"></i> Awaiting HR
                            </span>
                            @break
                        @case('hr_approved')
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                <i class="fas fa-calculator text-xs"></i> Awaiting Finance
                            </span>
                            @break
                        @case('finance_approved')
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                                <i class="fas fa-stamp text-xs"></i> Awaiting MD
                            </span>
                            @break
                        @case('md_approved')
                        @case('approved')
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                <i class="fas fa-check text-xs"></i> MD Approved
                            </span>
                            @break
                        @case('paid')
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                <i class="fas fa-money-bill-wave text-xs"></i> Paid
                            </span>
                            @break
                    @endswitch
                    @endif
                </td>

                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                    <a href="{{ route('account-manager.payroll.show', $run) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold
                              {{ in_array($run->status, ['draft','processing']) ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        @if(in_array($run->status, ['draft','processing']))
                            <i class="fas fa-play"></i> Open & Process
                        @elseif(in_array($run->status, ['md_approved','approved']))
                            <i class="fas fa-money-bill-wave"></i> Mark Paid
                        @else
                            <i class="fas fa-eye"></i> View
                        @endif
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-6 py-16 text-center text-slate-400">
                    <i class="fas fa-file-invoice-dollar text-4xl mb-3 block"></i>
                    No payroll runs found for this period.
                    @if($empType || $statusF)
                    <br><a href="{{ route('account-manager.payroll') }}" class="text-blue-500 hover:underline text-sm mt-1 inline-block">Clear filters</a>
                    @else
                    <br><button onclick="document.getElementById('new-run-modal').classList.remove('hidden')" class="mt-2 text-sm text-blue-600 hover:underline">
                        <i class="fas fa-plus mr-1"></i> Create a new payroll run
                    </button>
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($runs->hasPages())
<div class="mt-4">{{ $runs->appends(request()->all())->links() }}</div>
@endif

@endsection

