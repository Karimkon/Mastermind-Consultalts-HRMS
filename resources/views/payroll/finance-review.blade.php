@extends('layouts.app')
@section('title', 'Finance Review — ' . $payroll->title)
@section('content')

<x-page-header title="Finance Employee Review" :subtitle="$payroll->title . ' · ' . date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year))">
    <a href="{{ route('payroll.show', $payroll) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="mb-5 flex items-start gap-3 p-4 bg-purple-50 border border-purple-200 rounded-xl text-purple-800">
    <i class="fas fa-calculator text-xl mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-sm">Finance Payroll Review</p>
        <p class="text-sm mt-0.5">All approved employees are selected by default. <strong>Uncheck</strong> any employee whose payment should be withheld. Employees already withheld by HR are shown with a badge — you may override HR's decision if needed. Enter a reason for any withheld employees, then click <strong>"Finance Approve → MD"</strong>.</p>
    </div>
</div>

<form method="POST" action="{{ route('payroll.finance-approve', $payroll) }}" id="financeReviewForm">
@csrf

{{-- Top action bar --}}
<div class="card p-4 mb-4">
    <div class="flex flex-wrap items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" id="selectAll" checked onchange="toggleAll(this)"
                       class="w-4 h-4 rounded text-purple-600 border-slate-300">
                <span class="text-sm font-semibold text-slate-700">Select All / Deselect All</span>
            </label>
            <span class="text-sm text-slate-500">
                <span id="selectedCount" class="font-bold text-purple-700">{{ $payslips->where('payment_status','pending')->count() }}</span>
                of {{ $payslips->count() }} employees included
            </span>
            <span class="text-sm text-slate-500">
                Total Net: <span id="totalNet" class="font-bold text-emerald-700">UGX {{ number_format($payslips->where('payment_status','pending')->sum('net_salary')) }}</span>
            </span>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs text-slate-500 block mb-0.5">Reason for withheld employees</label>
                <input type="text" name="withheld_reason"
                       placeholder="e.g. Budget constraints — please review"
                       class="form-input text-sm py-1.5 w-80">
            </div>
            <button type="submit" onclick="return confirmApprove()"
                    data-loading-label="Approving…" data-loading-overlay
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white whitespace-nowrap"
                    style="background:#7c3aed;">
                <i class="fas fa-check-double"></i> Finance Approve → MD
            </button>
        </div>
    </div>

    {{-- Search/filter --}}
    <div class="mt-3 flex gap-3">
        <input type="text" id="searchInput" placeholder="Search employee name or number..."
               oninput="filterTable()"
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
@include('payroll.partials.review-table', ['payslips' => $payslips, 'accent' => 'purple', 'clearedAs' => 'Approved by HR'])

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
        return confirm('Finance Approve payroll?\n\n' + checked + ' employees will be INCLUDED.\n' + withheld + ' employees will be WITHHELD.\n\nSubmit to MD?');
    }
    return confirm('Finance Approve all ' + checked + ' employees and submit to MD?');
}
</script>
@endpush

@endsection
