@extends('layouts.app')
@section('title', $payroll->title)
@section('content')

<x-page-header title="{{ $payroll->title }}"
    subtitle="{{ date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year)) }} · {{ $payroll->client?->company_name ?? 'General Payroll' }}">
    <a href="{{ route('payroll.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>

    {{-- Manual Days Upload (draft/processing stage only) --}}
    @if(!$payroll->isLocked() && !$payroll->isImported() && in_array($payroll->status, ['draft','processing']))
    <div class="relative" x-data="{ showUpload: false }">
        <button @click="showUpload = !showUpload" class="btn-secondary flex items-center gap-1">
            <i class="fas fa-calendar-alt text-indigo-600"></i> Manual Days
            <i class="fas fa-chevron-down text-xs ml-1"></i>
        </button>
        <div x-show="showUpload" @click.away="showUpload = false" x-cloak
             class="absolute right-0 mt-1 w-64 bg-white border rounded-lg shadow-lg z-50 p-3">
            <p class="text-xs text-gray-500 mb-2">Upload CSV with days worked per employee. <a href="{{ route('payroll.manual-days-template', $payroll) }}" class="text-indigo-600 underline">Download template</a></p>
            <form method="POST" action="{{ route('payroll.import-manual-days', $payroll) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".csv,.xlsx,.xls" class="text-xs w-full mb-2 border rounded p-1" required>
                <button type="submit" class="w-full text-xs bg-indigo-600 text-white rounded px-3 py-1.5 hover:bg-indigo-700">Upload Days</button>
            </form>
        </div>
    </div>
    @endif

    {{-- Stage 1: AM processes --}}
    @if(!$payroll->isLocked() && !$payroll->isImported() && in_array($payroll->status, ['draft','processing']))
    <form method="POST" action="{{ route('payroll.process', $payroll) }}" class="inline">
        @csrf <button class="btn-primary"><i class="fas fa-play mr-1"></i> Run &amp; Submit to HR</button>
    </form>
    @endif

    {{-- Stage 2: HR reviews employees then approves --}}
    @if(!$payroll->isLocked() && $payroll->status === 'processed')
    @role('super-admin|hr-admin')
    <a href="{{ route('payroll.hr-review', $payroll) }}"
       class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700">
        <i class="fas fa-user-check"></i> Review Employees &amp; Approve
    </a>
    <button type="button" onclick="openSendBack('processed')"
            class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-medium bg-red-50 text-red-700 border border-red-200 hover:bg-red-100">
        <i class="fas fa-undo"></i> Send Back
    </button>
    @endrole
    @endif

    {{-- Stage 3: Finance reviews employees then approves --}}
    @if(!$payroll->isLocked() && $payroll->status === 'hr_approved')
    @role('super-admin|payroll-officer')
    <a href="{{ route('payroll.finance-review', $payroll) }}"
       class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold text-white"
       style="background:#7c3aed;">
        <i class="fas fa-calculator"></i> Review &amp; Finance Approve
    </a>
    <button type="button" onclick="openSendBack('hr_approved')"
            class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-medium bg-red-50 text-red-700 border border-red-200 hover:bg-red-100">
        <i class="fas fa-undo"></i> Send Back to HR
    </button>
    @endrole
    @endif

    {{-- Stage 4: MD reviews employees then gives final approval --}}
    @if(!$payroll->isLocked() && $payroll->status === 'finance_approved')
    @role('super-admin|md')
    <a href="{{ route('payroll.md-review', $payroll) }}"
       class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold text-white"
       style="background:#059669;">
        <i class="fas fa-stamp"></i> Review &amp; MD Approve
    </a>
    <button type="button" onclick="openSendBack('finance_approved')"
            class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-medium bg-red-50 text-red-700 border border-red-200 hover:bg-red-100">
        <i class="fas fa-undo"></i> Send Back to Finance
    </button>
    @endrole
    @endif

    {{-- After MD approval: mark as paid --}}
    @if(in_array($payroll->status, ['md_approved','approved']) && $payroll->isLocked())
    @role('super-admin|payroll-officer')
    @php
        $pendingCount  = $payroll->payslips->where('payment_status','pending')->count();
        $withheldCount = $payroll->payslips->where('payment_status','withheld')->count();
        $confirmMsg    = 'Mark payroll as paid? ' . $pendingCount . ' employees will be notified.' . ($withheldCount ? ' ' . $withheldCount . ' withheld employees will NOT be paid.' : '');
    @endphp
    <form method="POST" action="{{ route('payroll.mark-paid', $payroll) }}" class="inline"
          onsubmit="return confirm('{{ $confirmMsg }}')">
        @csrf
        <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700">
            <i class="fas fa-money-bill-wave"></i> Mark as Paid
        </button>
    </form>
    @endrole
    @endif

    {{-- KCB Payment Files: available as soon as payroll is processed --}}
    @if(in_array($payroll->status, ['processed','hr_approved','finance_approved','md_approved','approved','paid']))
    <a href="{{ route('payroll.payment-readiness', $payroll) }}"
       class="btn-secondary flex items-center gap-1 {{ ($totals['unpayable'] ?? 0) > 0 ? 'ring-2 ring-red-400' : '' }}">
        <i class="fas fa-clipboard-check {{ ($totals['unpayable'] ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600' }}"></i>
        Payment Readiness
        @if(($totals['unpayable'] ?? 0) > 0)
            <span class="ml-1 px-1.5 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-bold">{{ $totals['unpayable'] }}</span>
        @endif
    </a>
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open" class="btn-secondary flex items-center gap-1">
            <i class="fas fa-university text-emerald-600"></i>
            KCB Payment Files
            <i class="fas fa-chevron-down text-xs ml-1"></i>
        </button>
        <div x-show="open" @click.outside="open=false" x-cloak
             class="absolute right-0 mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
            <a href="{{ route('payroll.kcb-eft', $payroll) }}"
               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-university w-4 text-blue-600"></i> EFT Bank Transfer
            </a>
            <a href="{{ route('payroll.kcb-mtn', $payroll) }}"
               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-mobile-alt w-4 text-yellow-500"></i> MTN Mobile Money
            </a>
            <a href="{{ route('payroll.kcb-airtel', $payroll) }}"
               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-mobile-alt w-4 text-red-500"></i> Airtel Mobile Money
            </a>
            <div class="border-t border-gray-100 my-1"></div>
            <a href="{{ route('payroll.bank-export', $payroll) }}"
               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-list-ul w-4 text-slate-500"></i> Full Payment Schedule (CSV)
            </a>
        </div>
    </div>

    {{-- Payroll & statutory reports. The KCB files above carry only what the bank
         needs, so employee details and NSSF live here. --}}
    @can('reports.export')
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open" type="button" class="btn-secondary flex items-center gap-1">
            <i class="fas fa-file-excel text-green-600"></i>
            Payroll Reports
            <i class="fas fa-chevron-down text-xs ml-1"></i>
        </button>
        <div x-show="open" @click.outside="open=false" x-cloak
             class="absolute right-0 mt-1 w-72 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
            <a href="{{ route('payroll.export-nssf', $payroll) }}"
               class="flex items-start gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-shield-alt w-4 mt-0.5 text-purple-600"></i>
                <div>
                    <p class="font-medium">NSSF Contribution Schedule</p>
                    <p class="text-xs text-slate-400">5% employee + 10% employer, for filing</p>
                </div>
            </a>
            @if(in_array($payroll->status, ['hr_approved','finance_approved','md_approved','approved','paid']))
            <a href="{{ route('payroll.export-excel', $payroll) }}"
               class="flex items-start gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-file-excel w-4 mt-0.5 text-green-600"></i>
                <div>
                    <p class="font-medium">Full Payroll Excel</p>
                    <p class="text-xs text-slate-400">Employee details, PAYE, NSSF, net pay</p>
                </div>
            </a>
            @else
            <span class="flex items-start gap-3 px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed"
                  title="Available once HR has approved this run">
                <i class="fas fa-file-excel w-4 mt-0.5"></i>
                <div>
                    <p class="font-medium">Full Payroll Excel</p>
                    <p class="text-xs">Available after HR approval</p>
                </div>
            </span>
            @endif
            @if(in_array($payroll->status, ['md_approved','approved','paid']))
            <a href="{{ route('payroll.export-pdf', $payroll) }}"
               class="flex items-start gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-file-pdf w-4 mt-0.5 text-red-500"></i>
                <div>
                    <p class="font-medium">Summary PDF</p>
                    <p class="text-xs text-slate-400">Signed-off summary with approval chain</p>
                </div>
            </a>
            @endif
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

{{-- ── Approval Chain Next-Step Banner ─────────────────────────────── --}}
@if(!$payroll->isLocked())
@php
    $nextStep = match($payroll->status) {
        'draft','processing' =>
            ['icon'=>'fa-play','color'=>'blue','who'=>'Account Manager','action'=>'Run & Submit to HR','detail'=>'Process the payroll to send it to HR for review.'],
        'processed' =>
            ['icon'=>'fa-user-check','color'=>'orange','who'=>'HR Admin (hr@mastermind.co.za)','action'=>'Review Employees & Approve','detail'=>'HR must log in, review the employee list (select/deselect who gets paid), then approve to send to Finance.'],
        'hr_approved' =>
            ['icon'=>'fa-calculator','color'=>'purple','who'=>'Finance / Payroll Officer (payroll@mastermind.co.za)','action'=>'Finance Approve → MD','detail'=>'Finance must log in and click the "Finance Approve → MD" button above.'],
        'finance_approved' =>
            ['icon'=>'fa-stamp','color'=>'green','who'=>'MD (deo@mastermind.co.za)','action'=>'MD Final Approval','detail'=>'The MD must log in and give final approval, which locks the payroll.'],
        'md_approved','approved' =>
            ['icon'=>'fa-money-bill-wave','color'=>'green','who'=>'Finance / Payroll Officer','action'=>'Mark as Paid','detail'=>'After payments are made, click "Mark as Paid" to notify employees.'],
        default => null,
    };
@endphp
@if($nextStep)
<div class="mb-5 flex items-start gap-3 p-4 rounded-xl border
    {{ $nextStep['color']==='orange' ? 'bg-orange-50 border-orange-200 text-orange-800' :
       ($nextStep['color']==='purple' ? 'bg-purple-50 border-purple-200 text-purple-800' :
       ($nextStep['color']==='green'  ? 'bg-green-50 border-green-200 text-green-800'   :
                                        'bg-blue-50 border-blue-200 text-blue-800')) }}">
    <i class="fas {{ $nextStep['icon'] }} text-xl mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-sm">Next Step: {{ $nextStep['who'] }}</p>
        <p class="text-sm mt-0.5">{{ $nextStep['detail'] }}</p>
        <p class="text-xs mt-1 opacity-75">Button to click: <strong>"{{ $nextStep['action'] }}"</strong></p>
    </div>
</div>
@endif
@endif

{{-- ── Super Admin override ──────────────────────────────────────────────
     Once a run reached "paid" the screen offered nothing at all: no way to
     correct a run that went the wrong way, no way to move one whose stage
     owner has left, no way to delete one created by mistake. This is that
     way out, and it is deliberately the loudest thing on the page — it
     walks past HR, Finance and the MD in one click.

     MD accounts are excluded even when they also hold super-admin: the
     person who gives final approval must not be the person who can grant
     it without one. That is the same rule the rest of this controller
     already follows. --}}
@if(auth()->user()->hasRole('super-admin') && !auth()->user()->hasRole('md'))
<div x-data="{ open: false }" class="mb-5 border-2 border-dashed border-amber-300 rounded-xl bg-amber-50/60">
    <button type="button" @click="open = !open"
            class="w-full flex items-center gap-3 p-4 text-left">
        <i class="fas fa-user-shield text-amber-600 text-lg"></i>
        <div class="flex-1">
            <p class="font-semibold text-amber-900 text-sm">Super Admin override</p>
            <p class="text-xs text-amber-700">
                Move this run to any status, lock it, or delete it — outside the approval chain.
            </p>
        </div>
        <i class="fas fa-chevron-down text-amber-600 text-xs transition-transform"
           :class="{ 'rotate-180': open }"></i>
    </button>

    <div x-show="open" x-cloak class="px-4 pb-4 space-y-4 border-t border-amber-200 pt-4">

        {{-- Move to any status --}}
        <form method="POST" action="{{ route('payroll.override-status', $payroll) }}"
              onsubmit="return confirm('Move this run outside the approval chain? The reason you gave will be recorded against it permanently.')">
            @csrf
            <p class="text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Change status</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Move to</label>
                    <select name="status" class="form-input text-sm" required>
                        @foreach([
                            'draft'            => 'Draft — back to the Account Manager',
                            'processed'        => 'HR Review',
                            'hr_approved'      => 'Finance Review',
                            'finance_approved' => 'MD Review',
                            'md_approved'      => 'MD Approved (locks)',
                            'paid'             => 'Paid (locks)',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($payroll->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-slate-500 mb-1">
                        Reason <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="reason" class="form-input text-sm" required minlength="5"
                           placeholder="e.g. Paid in error by the old AM screen — pulling back to HR to redo">
                </div>
            </div>
            <div class="mt-3 flex items-center gap-3">
                <button class="btn-primary text-sm" style="background:#d97706;">
                    <i class="fas fa-shuffle mr-1"></i> Move run
                </button>
                <span class="text-xs text-slate-500">
                    Approvals for stages after the new status are cleared. Earlier ones are left alone —
                    this never invents an approval that did not happen. Payslip payment status is not changed.
                </span>
            </div>
        </form>

        {{-- Lock / unlock / edit / delete --}}
        <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-amber-200">
            @if($payroll->isLocked())
                <form method="POST" action="{{ route('payroll.unlock', $payroll) }}" class="inline">
                    @csrf
                    <button class="btn-secondary text-sm">
                        <i class="fas fa-lock-open mr-1"></i> Unlock
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('payroll.lock', $payroll) }}" class="inline"
                      onsubmit="return confirm('Lock this run? Every workflow button hides while it is locked. You can unlock it again.')">
                    @csrf
                    <button class="btn-secondary text-sm">
                        <i class="fas fa-lock mr-1"></i> Lock
                    </button>
                </form>
            @endif

            <a href="{{ route('payroll.edit', $payroll) }}" class="btn-secondary text-sm">
                <i class="fas fa-pen mr-1"></i> Edit details
            </a>

            <form method="POST" action="{{ route('payroll.destroy', $payroll) }}" class="inline ml-auto"
                  onsubmit="return confirm('Delete {{ addslashes($payroll->title) }} and all {{ $payroll->payslips->count() }} of its payslips? This cannot be undone. A record of the deletion is kept in the audit log.')">
                @csrf @method('DELETE')
                <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    <i class="fas fa-trash"></i> Delete run
                </button>
            </form>
        </div>
    </div>
</div>
@endif
{{-- Imported-run banner.

     Somebody reading these figures needs to know the engine did not produce
     them before they try to reconcile them against a salary record, and before
     they reach for Process to "refresh" the run. --}}
@if($payroll->isImported())
<div class="mb-5 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800">
    <i class="fas fa-file-import text-xl mt-0.5"></i>
    <div>
        <p class="font-semibold">These payslips were imported, not calculated.</p>
        <p class="text-sm">
            They record what was actually paid{{ $payroll->imported_at ? ' and were loaded on '.$payroll->imported_at->format('j M Y') : '' }}.
            The payroll engine cannot reproduce them, so this run can no longer be processed —
            re-running it would replace the real figures with ones derived from today's salary records.
            Corrections belong in a new run.
        </p>
        @if($payroll->import_note)
        <p class="text-sm mt-1 italic">{{ $payroll->import_note }}</p>
        @endif
    </div>
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
        {{-- A run locked before MD approval cannot advance: every approval button is
             hidden while locked, so say plainly that it needs unlocking. --}}
        @if(!in_array($payroll->status, ['md_approved','approved','paid']))
        <p class="text-sm mt-1 font-medium">
            <i class="fas fa-triangle-exclamation mr-1"></i>
            This run was locked before MD approval, so the approval workflow cannot continue.
            @if(auth()->user()->hasRole('super-admin'))
                Use <strong>Unlock</strong> above to release it.
            @else
                A Super Admin needs to unlock it.
            @endif
        </p>
        @endif
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

{{-- ── Calendar & Pro-Rata Panel ─────────────────────────────────────── --}}
@php
    $calService  = app(\App\Services\CalendarService::class);
    $calSummary  = $calService->monthSummary($payroll->year, $payroll->month);
    $monthLabel  = date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year));
@endphp
<div class="card p-5 mb-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-2">
            <i class="fas fa-calendar-alt text-indigo-500"></i>
            {{ $monthLabel }} — Calendar &amp; Pro-Rata Info
        </h3>
        <span class="text-xs text-slate-400">Formula: Annual ÷ 365 × days worked</span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-4">
        <div class="text-center">
            <p class="text-2xl font-bold text-slate-800">{{ $calSummary['calendar_days'] }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Calendar Days</p>
        </div>
        <div class="text-center">
            <p class="text-2xl font-bold text-slate-600">{{ $calSummary['working_days'] }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Weekdays (Mon–Fri)</p>
        </div>
        <div class="text-center">
            <p class="text-2xl font-bold text-emerald-600">{{ $calSummary['paid_holidays'] }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Paid Public Holidays</p>
        </div>
        <div class="text-center">
            <p class="text-2xl font-bold text-indigo-600">365</p>
            <p class="text-xs text-slate-500 mt-0.5">Annual Divisor</p>
        </div>
        <div class="text-center bg-indigo-50 rounded-xl p-2">
            <p class="text-xs text-indigo-500 font-semibold uppercase mb-1">Example (100k/month)</p>
            <p class="text-sm font-bold text-indigo-800">
                {{ number_format(round(1200000 / 365 * $calSummary['calendar_days'], 0)) }} UGX
            </p>
            <p class="text-xs text-indigo-400">full {{ $calSummary['calendar_days'] }} days</p>
        </div>
    </div>
    @if($calSummary['holidays']->isNotEmpty())
    <div class="border-t border-slate-100 pt-3">
        <p class="text-xs font-medium text-slate-500 mb-2">Public Holidays this month (paid by default):</p>
        <div class="flex flex-wrap gap-2">
            @foreach($calSummary['holidays'] as $h)
            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full
                {{ $h->type === 'national' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                <i class="fas {{ $h->type === 'national' ? 'fa-flag' : 'fa-moon' }} text-xs"></i>
                {{ \Carbon\Carbon::parse($h->date)->format('j M') }} – {{ $h->name }}
                @if(!$h->is_paid) <span class="text-red-500">(unpaid)</span> @endif
            </span>
            @endforeach
        </div>
    </div>
    @else
    <div class="border-t border-slate-100 pt-3 text-xs text-slate-400 flex items-center gap-2">
        <i class="fas fa-calendar-check text-green-400"></i>
        No public holidays in {{ $monthLabel }}. All attendance days count as worked.
    </div>
    @endif
</div>

{{-- Pre-payment warnings --}}
@if(($totals['unpayable'] ?? 0) > 0 || ($totals['zero_net'] ?? 0) > 0)
<div class="mb-6 space-y-3">
    @if(($totals['unpayable'] ?? 0) > 0)
    <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800 flex items-start gap-3">
        <i class="fas fa-triangle-exclamation mt-0.5"></i>
        <div>
            <p class="font-semibold">{{ $totals['unpayable'] }} employee(s) are missing bank or mobile money details.</p>
            <p>They will be left out of the KCB payment files.
               <a href="{{ route('payroll.payment-readiness', $payroll) }}" class="underline font-medium">Review payment readiness</a>
               before you download.</p>
        </div>
    </div>
    @endif
    @if(($totals['zero_net'] ?? 0) > 0)
    <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800 flex items-start gap-3">
        <i class="fas fa-circle-info mt-0.5"></i>
        <div>
            <p class="font-semibold">{{ $totals['zero_net'] }} of {{ $totals['count'] }} payslips have zero net pay.</p>
            <p>Usually this means days worked were never uploaded for them. Check the Manual Days upload before approving.</p>
        </div>
    </div>
    @endif
</div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-7 gap-4 mb-6">
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
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">NSSF (Employee 5%)</p>
        <p class="text-xl font-bold text-purple-600">UGX {{ number_format($totals['nssf'], 0) }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">NSSF (Employer 10%)</p>
        <p class="text-xl font-bold text-indigo-600">UGX {{ number_format($totals['nssf_employer'] ?? 0, 0) }}</p>
        <p class="text-[10px] text-slate-400 mt-0.5">Total remitted UGX {{ number_format($totals['nssf_total'] ?? 0, 0) }}</p>
    </div>
    <div class="card p-4 text-center border-2 border-emerald-200">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Total Net Pay</p>
        <p class="text-xl font-bold text-emerald-700">UGX {{ number_format($totals['net'], 0) }}</p>
    </div>
</div>

{{-- Payslips table --}}
@php
    // Emailing a payslip is a communication action, not an edit, so it stays
    // available on a locked run — Finance still needs it after Mark as Paid.
    $canEmailPayslips = auth()->user()->hasAnyRole(['super-admin','hr-admin','payroll-officer','account-manager']);
    $emailableCount   = $canEmailPayslips
        ? $payroll->payslips->filter(fn ($s) => filled($s->employee?->user?->email))->count()
        : 0;
@endphp
<div class="card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
        <h2 class="font-semibold text-slate-800">Payslips ({{ $totals['count'] }})</h2>
        <div class="flex items-center gap-4">
            @if($canEmailPayslips && $emailableCount > 0)
            <form method="POST" action="{{ route('payroll.email-all-payslips', $payroll) }}"
                  onsubmit="return confirm('Email the payslip PDF to {{ $emailableCount }} employee(s)? This cannot be undone.')">
                @csrf
                <button type="submit" class="btn-secondary text-sm flex items-center gap-1">
                    <i class="fas fa-paper-plane text-blue-600"></i>
                    Email All Payslips
                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold">{{ $emailableCount }}</span>
                </button>
            </form>
            @endif
            @if($totals['count'] > 0)
            <a href="{{ route('payroll.payslips', $payroll) }}" class="text-sm text-blue-600 hover:underline whitespace-nowrap">
                View all payslips <i class="fas fa-arrow-right ml-1"></i>
            </a>
            @endif
        </div>
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
                <th class="table-head px-4 py-3 text-right">NSSF 5%</th>
                <th class="table-head px-4 py-3 text-right">Net Pay</th>
                <th class="table-head px-4 py-3 text-center">Days</th>
                <th class="table-head px-4 py-3 text-left">Payslip</th>
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
                <td class="px-4 py-3 text-right text-sm text-purple-600">-{{ number_format($slip->employeeNssf(), 0) }}</td>
                <td class="px-4 py-3 text-right text-sm font-bold text-emerald-700">UGX {{ number_format($slip->net_salary, 0) }}</td>
                <td class="px-4 py-3 text-center text-xs text-slate-500">
                    <span class="text-green-600 font-medium">{{ $slip->worked_days }}P</span> /
                    <span class="text-red-400">{{ $slip->absent_days }}A</span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('payroll.payslip.pdf', [$payroll, $slip->employee]) }}"
                           class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800 font-medium">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        @if($canEmailPayslips)
                            @php $slipEmail = $slip->employee->user?->email; @endphp
                            @if($slipEmail)
                            <form method="POST" action="{{ route('payroll.payslip.email', [$payroll, $slip->employee]) }}"
                                  onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Sending';">
                                @csrf
                                <button type="submit" title="Email this payslip to {{ $slipEmail }}"
                                        class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium disabled:opacity-50">
                                    <i class="fas fa-envelope"></i> Email
                                </button>
                            </form>
                            @else
                            <span class="inline-flex items-center gap-1 text-xs text-slate-300 cursor-not-allowed"
                                  title="This employee has no email address on file">
                                <i class="fas fa-envelope"></i> Email
                            </span>
                            @endif
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="py-12 text-center">
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
{{-- ── Approval Comments / Send-Back History ─────────────────────── --}}
@php $comments = $payroll->comments()->with('user')->get(); @endphp
@if($comments->isNotEmpty())
<div class="card p-6 mt-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
        <i class="fas fa-comments text-orange-500"></i> Approval Comments & Send-Back History
    </h3>
    <div class="space-y-3">
        @foreach($comments as $c)
        <div class="flex gap-3 p-3 rounded-lg {{ $c->action === 'sent_back' ? 'bg-red-50 border border-red-100' : 'bg-blue-50 border border-blue-100' }}">
            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                {{ $c->action === 'sent_back' ? 'bg-red-200 text-red-700' : 'bg-blue-200 text-blue-700' }}">
                <i class="fas {{ $c->action === 'sent_back' ? 'fa-undo' : 'fa-check' }}"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-slate-700">{{ $c->user?->name ?? 'System' }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        {{ $c->action === 'sent_back' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ $c->action === 'sent_back' ? 'Sent Back' : 'Approved' }}
                    </span>
                    <span class="text-xs text-slate-400">
                        {{ ucfirst(str_replace('_',' ',$c->from_status)) }} → {{ ucfirst(str_replace('_',' ',$c->to_status)) }}
                    </span>
                    <span class="text-xs text-slate-400 ml-auto">{{ $c->created_at->format('d M Y H:i') }}</span>
                </div>
                <p class="text-sm text-slate-700 mt-1 italic">"{{ $c->comment }}"</p>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Send Back Modal ────────────────────────────────────────────── --}}
<div id="sendBackModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 mx-4">
        <h3 class="text-lg font-bold text-slate-800 mb-1 flex items-center gap-2">
            <i class="fas fa-undo text-red-500"></i> Send Back Payroll
        </h3>
        <p class="text-sm text-slate-500 mb-4">Provide a clear reason so the recipient knows exactly what to fix.</p>
        <form method="POST" action="{{ route('payroll.send-back', $payroll) }}" id="sendBackForm">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Reason / Comments <span class="text-red-500">*</span></label>
                <textarea name="comment" rows="5" required
                    placeholder="e.g. We don't have enough budget to cover this payroll. Please reduce overtime claims for Banda branch and resubmit.&#10;&#10;Or: Employee ROF045 should not receive payment this month due to disciplinary case."
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none resize-none"></textarea>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeSendBack()"
                    class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 text-slate-600 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    <i class="fas fa-paper-plane mr-1"></i> Send Back with Comment
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openSendBack(status) {
    document.getElementById('sendBackModal').classList.remove('hidden');
}
function closeSendBack() {
    document.getElementById('sendBackModal').classList.add('hidden');
}
document.getElementById('sendBackModal').addEventListener('click', function(e) {
    if (e.target === this) closeSendBack();
});
</script>
@endpush

@endsection
