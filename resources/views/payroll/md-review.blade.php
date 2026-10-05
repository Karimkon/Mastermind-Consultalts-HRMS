@extends('layouts.app')
@section('title', 'MD Review — ' . $payroll->title)
@section('content')

<x-page-header title="MD Final Review" :subtitle="$payroll->title . ' · ' . date('F Y', mktime(0,0,0,$payroll->month,1,$payroll->year))">
    <a href="{{ route('payroll.show', $payroll) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="mb-5 flex items-start gap-3 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800">
    <i class="fas fa-stamp text-xl mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-sm">MD Final Approval</p>
        <p class="text-sm mt-0.5">All Finance-approved employees are selected by default. <strong>Uncheck</strong> any employee whose payment should be withheld at the MD level. Employees already withheld by HR or Finance are shown with badges. Clicking <strong>"MD Final Approval"</strong> will lock the payroll permanently.</p>
    </div>
</div>

<form method="POST" action="{{ route('payroll.approve', $payroll) }}" id="mdReviewForm">
@csrf

{{-- Top action bar --}}
<div class="card p-4 mb-4">
    <div class="flex flex-wrap items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" id="selectAll" checked onchange="toggleAll(this)"
                       class="w-4 h-4 rounded text-green-600 border-slate-300">
                <span class="text-sm font-semibold text-slate-700">Select All / Deselect All</span>
            </label>
            <span class="text-sm text-slate-500">
                <span id="selectedCount" class="font-bold text-green-700">{{ $payslips->where('payment_status','pending')->count() }}</span>
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
                       placeholder="e.g. MD decision — pending board review"
                       class="form-input text-sm py-1.5 w-80">
            </div>
            <button type="submit" onclick="return confirmApprove()"
                    data-loading-label="Approving…" data-loading-overlay
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white whitespace-nowrap"
                    style="background:#059669;">
                <i class="fas fa-stamp"></i> MD Final Approval
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
@include('payroll.partials.review-table', ['payslips' => $payslips, 'accent' => 'green', 'clearedAs' => 'Finance Approved'])

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
            alert('Please enter a reason for the ' + withheld + ' withheld employee(s) before final approval.');
            return false;
        }
        return confirm('MD Final Approval?\n\n' + checked + ' employees INCLUDED for payment.\n' + withheld + ' employees WITHHELD.\n\nThis will LOCK the payroll. Proceed?');
    }
    return confirm('MD Final Approval for all ' + checked + ' employees?\n\nThis will LOCK the payroll. Proceed?');
}
</script>
@endpush

@endsection
