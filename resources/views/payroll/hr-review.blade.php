@extends('layouts.app')
@section('title', 'HR Review — ' . $payroll->title)
@section('content')

<x-page-header title="HR Employee Review" :subtitle="$payroll->title . ' · ' . date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year))">
    <a href="{{ route('payroll.show', $payroll) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="mb-5 flex items-start gap-3 p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-800">
    <i class="fas fa-user-check text-xl mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-sm">HR Payroll Review</p>
        @if($canAct)
        <p class="text-sm mt-0.5">All employees are selected by default. <strong>Uncheck</strong> any employee whose payment should be withheld this cycle — e.g. disciplinary case, dispute, or absence without leave. Enter a reason for withheld employees, then click <strong>"Approve &amp; Submit to Finance"</strong>.</p>
        @else
        {{-- Telling somebody to press a button he has not been given is worse
             than telling him nothing. --}}
        <p class="text-sm mt-0.5">What HR is reviewing for this run, as they see it. Approving or withholding anybody here is HR's to do — this is a read-only view.</p>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('payroll.hr-approve', $payroll) }}" id="hrReviewForm">
@csrf

{{-- Top action bar --}}
<div class="card p-4 mb-4">
    <div class="flex flex-wrap items-center gap-4 justify-between">
        {{-- Select all + counter --}}
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer select-none" @class(['hidden' => ! $canAct])>
                <input type="checkbox" id="selectAll" checked onchange="toggleAll(this)"
                       class="w-4 h-4 rounded text-blue-600 border-slate-300">
                <span class="text-sm font-semibold text-slate-700">Select All / Deselect All</span>
            </label>
            <span class="text-sm text-slate-500">
                <span id="selectedCount" class="font-bold text-blue-700">{{ $payslips->count() }}</span>
                of {{ $payslips->count() }} employees included
            </span>
            <span class="text-sm text-slate-500">
                Total Net: <span id="totalNet" class="font-bold text-emerald-700">UGX {{ number_format($payslips->sum('net_salary')) }}</span>
            </span>
        </div>

        {{-- Withheld reason + submit. Hidden for somebody admitted to
             look but not to act: the MD can read every stage, and pressing
             another stage's approve button would only earn him a 403. --}}
        @if(! $canAct)
        <div class="text-sm text-slate-500 italic">
            <i class="fas fa-eye mr-1"></i> Viewing only — HR approves this stage.
        </div>
        @else
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs text-slate-500 block mb-0.5">Reason for withheld employees</label>
                <input type="text" name="withheld_reason"
                       placeholder="e.g. Disciplinary action pending investigation"
                       class="form-input text-sm py-1.5 w-80">
            </div>
            <button type="submit" onclick="return confirmApprove()"
                    data-loading-label="Approving…" data-loading-overlay
                    class="btn-primary flex items-center gap-2 whitespace-nowrap">
                <i class="fas fa-check-double"></i> Approve &amp; Submit to Finance
            </button>
        </div>
        @endif
    </div>

    {{-- Search/filter bar --}}
    <div class="mt-3 flex gap-3">
        <input type="text" id="searchInput" placeholder="Search employee name or number..."
               oninput="filterTable(this.value)"
               class="form-input text-sm py-1.5 w-72">
        <select id="deptFilter" onchange="filterTable()" class="form-input text-sm py-1.5 w-48">
            <option value="">All Departments</option>
            @foreach($payslips->pluck('employee.department.name')->unique()->filter()->sort() as $dept)
            <option value="{{ $dept }}">{{ $dept }}</option>
            @endforeach
        </select>
        <button type="button" onclick="showOnlyDeselected()"
                class="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
            Show withheld only
        </button>
        <button type="button" onclick="showAll()"
                class="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
            Show all
        </button>
    </div>
</div>

{{-- Employee table --}}
@include('payroll.partials.review-table', ['payslips' => $payslips, 'accent' => 'blue', 'clearedAs' => 'Pending HR'])

</form>

@push('scripts')
@include('payroll.partials.review-script')
<script>
function confirmApprove() {
    const checked  = document.querySelectorAll('.slip-checkbox:checked').length;
    const total    = document.querySelectorAll('.slip-checkbox').length;
    const withheld = total - checked;

    if (withheld > 0) {
        const reason = document.querySelector('[name=withheld_reason]').value.trim();
        if (!reason) {
            alert('Please enter a reason for the ' + withheld + ' withheld employee(s) before approving.');
            return false;
        }
        return confirm('Approve payroll?\n\n' + checked + ' employees will be INCLUDED for payment.\n' + withheld + ' employees will be WITHHELD.\n\nSubmit to Finance?');
    }
    return confirm('Approve all ' + checked + ' employees and submit to Finance?');
}
</script>
@endpush

@endsection
