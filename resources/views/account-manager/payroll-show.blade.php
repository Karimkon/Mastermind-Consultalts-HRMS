@extends("layouts.app")
@section("title", $run->title)
@section("content")

<x-page-header :title="$run->title"
    subtitle="{{ date('F Y', mktime(0,0,0,$run->month,1,$run->year)) }} · {{ $run->client?->company_name ?? 'General' }}">
    <a href="{{ route('account-manager.payroll') }}" class="btn-secondary text-sm">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>

    {{-- DRAFT: Manual Days Upload + Process button --}}
    @if(in_array($run->status, ['draft','processing']) && !$run->isLocked())
    <div class="relative" x-data="{ showUpload: false }">
        <button @click="showUpload = !showUpload" class="btn-secondary flex items-center gap-1">
            <i class="fas fa-calendar-alt text-indigo-600"></i> Manual Days
            <i class="fas fa-chevron-down text-xs ml-1"></i>
        </button>
        <div x-show="showUpload" @click.away="showUpload = false" x-cloak
             class="absolute right-0 mt-1 w-72 bg-white border rounded-xl shadow-xl z-50 p-4">
            <p class="text-xs font-semibold text-slate-700 mb-1">Upload Days Worked</p>
            <p class="text-xs text-slate-500 mb-2">
                {{ $manualDaysCount }} employees already have days loaded.
                <a href="{{ route('account-manager.payroll.manual-days-template', $run) }}"
                   class="text-indigo-600 underline">Download template</a>
            </p>
            <form method="POST" action="{{ route('account-manager.payroll.import-manual-days', $run) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".csv,.xlsx,.xls"
                       class="text-xs w-full mb-2 border rounded-lg p-1.5" required>
                <button type="submit"
                        class="w-full text-xs bg-indigo-600 text-white rounded-lg px-3 py-2 font-semibold hover:bg-indigo-700">
                    <i class="fas fa-upload mr-1"></i> Upload Days
                </button>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('account-manager.payroll.process', $run) }}"
          onsubmit="return confirm('Process payroll for {{ $eligibleCount }} employees and submit to HR for review?')">
        @csrf
        <button class="btn-primary flex items-center gap-2">
            <i class="fas fa-play"></i> Run &amp; Submit to HR
        </button>
    </form>
    @endif

</x-page-header>

@if(session('success'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
</div>
@endif

{{-- ── Workflow Progress Bar ────────────────────────────────────────── --}}
@php
$stageNum = match($run->status) {
    'draft','processing' => 0,
    'processed'          => 1,
    'hr_approved'        => 2,
    'finance_approved'   => 3,
    'md_approved','approved' => 4,
    'paid'               => 5,
    default              => 0,
};
$stages = [
    ['label'=>'AM Processes', 'icon'=>'fa-cogs',        'done' => $stageNum > 0],
    ['label'=>'HR Review',    'icon'=>'fa-user-check',  'done' => $stageNum > 1],
    ['label'=>'Finance',      'icon'=>'fa-calculator',  'done' => $stageNum > 2],
    ['label'=>'MD Approval',  'icon'=>'fa-stamp',       'done' => $stageNum > 3],
    ['label'=>'Paid',         'icon'=>'fa-check-circle','done' => $stageNum > 4],
];
@endphp
<div class="card p-5 mb-5">
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Approval Workflow</h3>
    <div class="flex items-start justify-between relative">
        <div class="absolute top-5 left-0 right-0 h-0.5 bg-slate-200 z-0" style="margin:0 10%"></div>
        @foreach($stages as $i => $s)
        @php $current = $stageNum === $i; @endphp
        <div class="flex flex-col items-center z-10 flex-1">
            <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 mb-2
                {{ $s['done'] ? 'bg-green-500 border-green-500 text-white' : ($current ? 'bg-blue-500 border-blue-500 text-white animate-pulse' : 'bg-white border-slate-200 text-slate-400') }}">
                <i class="fas {{ $s['icon'] }} text-sm"></i>
            </div>
            <p class="text-xs font-semibold text-slate-700 text-center">{{ $s['label'] }}</p>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Next Step Banner ──────────────────────────────────────────────── --}}
@php
$banner = match($run->status) {
    'draft','processing' => ['color'=>'blue',  'icon'=>'fa-play',          'who'=>'You (Account Manager)', 'msg'=>"Click \"Run & Submit to HR\" above. System will calculate all {$eligibleCount} employees using Annual÷365×days formula."],
    'processed'          => ['color'=>'orange','icon'=>'fa-user-check',    'who'=>'HR Admin',             'msg'=>'HR must log in → Payroll → Review Employees & Approve to send to Finance.'],
    'hr_approved'        => ['color'=>'purple','icon'=>'fa-calculator',    'who'=>'Finance / Payroll Officer','msg'=>'Finance must log in → Payroll → Finance Review & Approve to send to MD.'],
    'finance_approved'   => ['color'=>'green', 'icon'=>'fa-stamp',         'who'=>'Managing Director (MD)', 'msg'=>'MD must log in → Payroll → MD Review & Final Approval. Payroll locks after this.'],
    'md_approved','approved'=>['color'=>'green','icon'=>'fa-money-bill-wave','who'=>'You (Account Manager)','msg'=>'MD has approved. Use the "Mark as Paid" form below after disbursing salaries.'],
    'paid'               => ['color'=>'green', 'icon'=>'fa-check-circle',  'who'=>'Complete',             'msg'=>'Payroll is fully paid. All employees have been notified.'],
    default              => null,
};
@endphp
@if($banner)
@php $clr = $banner['color']; @endphp
<div class="mb-5 flex items-start gap-3 p-4 rounded-xl border
    {{ $clr==='blue'  ? 'bg-blue-50 border-blue-200 text-blue-800' :
       ($clr==='orange'? 'bg-orange-50 border-orange-200 text-orange-800' :
       ($clr==='purple'? 'bg-purple-50 border-purple-200 text-purple-800' :
                         'bg-green-50 border-green-200 text-green-800')) }}">
    <i class="fas {{ $banner['icon'] }} text-xl mt-0.5"></i>
    <div>
        <p class="font-bold text-sm">Next Action: {{ $banner['who'] }}</p>
        <p class="text-sm mt-0.5">{{ $banner['msg'] }}</p>
    </div>
</div>
@endif

{{-- ── Summary Cards ────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ $payslips->count() ?: $eligibleCount }}</p>
        <p class="text-xs text-slate-500 mt-1">{{ $payslips->count() ? 'Payslips' : 'Employees Ready' }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase font-semibold mb-1">Manual Days Loaded</p>
        <p class="text-2xl font-bold text-indigo-600">{{ $manualDaysCount }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-slate-700">{{ number_format($payslips->sum('gross_salary')) }}</p>
        <p class="text-xs text-slate-500 mt-1">Gross (UGX)</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-amber-600">{{ number_format($payslips->sum('tax_amount')) }}</p>
        <p class="text-xs text-slate-500 mt-1">PAYE Tax</p>
    </div>
    <div class="card p-4 text-center border-l-4 border-emerald-500">
        <p class="text-lg font-bold text-emerald-700">{{ number_format($payslips->sum('net_salary')) }}</p>
        <p class="text-xs text-slate-500 mt-1">Net Pay (UGX)</p>
    </div>
</div>

{{-- ── Mark as Paid form ────────────────────────────────────────────── --}}
@if(in_array($run->status, ['approved', 'processed', 'md_approved']))
<div class="bg-white border border-emerald-200 rounded-xl p-5 mb-6" x-data="{ open: false }">
    <button @click="open = !open"
            class="flex items-center gap-2 text-emerald-700 font-semibold text-sm hover:text-emerald-900">
        <i class="fas fa-money-bill-wave text-emerald-500"></i>
        Mark as Paid
        <i class="fas fa-chevron-down text-xs" :class="{ 'rotate-180': open }"></i>
    </button>
    <div x-show="open" x-transition class="mt-4">
        <form method="POST" action="{{ route('account-manager.payroll.mark-paid', $run) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Payment Method *</label>
                    <select name="payment_method" class="form-input" required>
                        <option value="">— Select —</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Reference</label>
                    <input type="text" name="payment_reference" class="form-input" placeholder="TXN-20260501-001">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Payment Date</label>
                    <input type="date" name="payment_date" class="form-input" value="{{ today()->format('Y-m-d') }}">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn-primary"
                        onclick="return confirm('Confirm marking this payroll as PAID? This cannot be undone.')">
                    <i class="fas fa-check mr-1"></i> Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ── Payslips Table ───────────────────────────────────────────────── --}}
<div class="card overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800">
            @if($payslips->count())
                Payslips ({{ $payslips->count() }})
            @else
                Employees to be processed ({{ $eligibleCount }})
            @endif
        </h2>
    </div>
    @if($payslips->count())
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head px-6 py-3 text-left">Employee</th>
                    <th class="table-head px-4 py-3 text-left">Department</th>
                    <th class="table-head px-4 py-3 text-right">Days</th>
                    <th class="table-head px-4 py-3 text-right">Basic</th>
                    <th class="table-head px-4 py-3 text-right">Gross</th>
                    <th class="table-head px-4 py-3 text-right">PAYE</th>
                    <th class="table-head px-4 py-3 text-right font-bold text-emerald-700">Net Pay</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($payslips as $p)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3">
                        <p class="font-semibold text-slate-800 text-sm">{{ $p->employee?->full_name ?? '—' }}</p>
                        <p class="text-xs text-slate-400">{{ $p->employee?->emp_number ?? '' }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $p->employee?->department?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-sm font-medium text-slate-700">{{ $p->worked_days }}</td>
                    <td class="px-4 py-3 text-right font-mono text-slate-700 text-sm">{{ number_format($p->basic_salary) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-slate-700 text-sm">{{ number_format($p->gross_salary) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-amber-600 text-sm">{{ number_format($p->tax_amount) }}</td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700">{{ number_format($p->net_salary) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-100 font-bold text-sm">
                <tr>
                    <td colspan="3" class="px-6 py-3 text-slate-700">TOTALS</td>
                    <td class="px-4 py-3 text-right font-mono">{{ number_format($payslips->sum('basic_salary')) }}</td>
                    <td class="px-4 py-3 text-right font-mono">{{ number_format($payslips->sum('gross_salary')) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-amber-700">{{ number_format($payslips->sum('tax_amount')) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-emerald-800">{{ number_format($payslips->sum('net_salary')) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
    <div class="py-16 text-center">
        <i class="fas fa-calculator text-4xl text-slate-200 mb-4 block"></i>
        <p class="text-slate-500 font-medium">{{ $eligibleCount }} employees ready to be processed.</p>
        <p class="text-slate-400 text-sm mt-1">Click <strong>"Run &amp; Submit to HR"</strong> above to generate payslips.</p>
        @if($manualDaysCount > 0)
        <p class="text-indigo-600 text-sm mt-2"><i class="fas fa-check-circle mr-1"></i>{{ $manualDaysCount }} employees have manual days already loaded.</p>
        @endif
    </div>
    @endif
</div>

@endsection
