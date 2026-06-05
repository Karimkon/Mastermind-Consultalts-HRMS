@extends('layouts.app')
@section('title', 'Release Payments — ' . $payroll->title)
@section('content')

<x-page-header title="Release Payments" :subtitle="$payroll->title">
    <a href="{{ route('payroll.show', $payroll) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="mb-5 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800">
    <i class="fas fa-info-circle text-xl mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold text-sm">Selective Payment Release</p>
        <p class="text-sm mt-0.5">All employees are selected by default. <strong>Uncheck</strong> any employee whose payment should be withheld (e.g. disciplinary action, dispute). Provide a reason for withheld employees, then click <strong>"Release Selected Payments"</strong>.</p>
    </div>
</div>

<form method="POST" action="{{ route('payroll.submit-selective-pay', $payroll) }}" id="selectiveForm">
@csrf

{{-- Summary bar --}}
<div class="card p-4 mb-4 flex flex-wrap items-center gap-4 justify-between">
    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" id="selectAll" checked onchange="toggleAll(this)"
                   class="w-4 h-4 rounded text-emerald-600 border-slate-300">
            <span class="text-sm font-semibold text-slate-700">Select All / Deselect All</span>
        </label>
        <span class="text-sm text-slate-500">
            <span id="selectedCount" class="font-bold text-emerald-700">{{ $payslips->count() }}</span>
            / {{ $payslips->count() }} selected
        </span>
    </div>
    <div class="flex items-center gap-3">
        <div>
            <label class="text-xs text-slate-500 block mb-0.5">Reason for withheld employees</label>
            <input type="text" name="withheld_reason" placeholder="e.g. Disciplinary action — under investigation"
                   class="form-input text-sm py-1.5 w-72"
                   value="Payment withheld pending review.">
        </div>
        <button type="submit" onclick="return confirmRelease()"
                class="btn-primary bg-emerald-600 hover:bg-emerald-700 flex items-center gap-2 whitespace-nowrap">
            <i class="fas fa-paper-plane"></i> Release Selected Payments
        </button>
    </div>
</div>

{{-- Employee table --}}
<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
            <th class="px-4 py-3 text-left w-8"><i class="fas fa-check text-xs text-slate-400"></i></th>
            <th class="px-4 py-3 text-left">Employee</th>
            <th class="px-4 py-3 text-left">Department</th>
            <th class="px-4 py-3 text-right">Basic</th>
            <th class="px-4 py-3 text-right">Gross</th>
            <th class="px-4 py-3 text-right">Deductions</th>
            <th class="px-4 py-3 text-right font-bold text-emerald-700">Net Pay</th>
            <th class="px-4 py-3 text-center">Status</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100" id="tableBody">
        @foreach($payslips as $slip)
        <tr class="hover:bg-slate-50 transition-colors employee-row" id="row-{{ $slip->id }}"
            data-net="{{ $slip->net_salary }}">
            <td class="px-4 py-3">
                <input type="checkbox" name="selected_payslips[]" value="{{ $slip->id }}"
                       checked onchange="updateCount()"
                       class="w-4 h-4 rounded text-emerald-600 border-slate-300 slip-checkbox">
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                    <img src="{{ $slip->employee->avatar_url }}" class="w-7 h-7 rounded-full object-cover">
                    <div>
                        <p class="font-medium text-slate-800">{{ $slip->employee->full_name }}</p>
                        <p class="text-xs text-slate-400">{{ $slip->employee->emp_number }}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-slate-500 text-xs">{{ $slip->employee->department?->name ?? '—' }}</td>
            <td class="px-4 py-3 text-right text-slate-600">{{ number_format($slip->basic_salary) }}</td>
            <td class="px-4 py-3 text-right text-slate-700">{{ number_format($slip->gross_salary) }}</td>
            <td class="px-4 py-3 text-right text-orange-600">{{ number_format($slip->total_deductions) }}</td>
            <td class="px-4 py-3 text-right font-bold text-emerald-700 net-cell">
                UGX {{ number_format($slip->net_salary) }}
            </td>
            <td class="px-4 py-3 text-center">
                @if($slip->payment_status === 'paid')
                    <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">Paid</span>
                @elseif($slip->payment_status === 'withheld')
                    <span class="text-xs px-2 py-0.5 bg-red-100 text-red-700 rounded-full font-medium">Withheld</span>
                @else
                    <span class="text-xs px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full">Pending</span>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
        <tfoot class="bg-slate-50 border-t-2 border-slate-200">
        <tr>
            <td colspan="6" class="px-4 py-3 text-sm font-semibold text-slate-600">
                Total to release: <span id="totalCount" class="text-emerald-700">{{ $payslips->count() }}</span> employees
            </td>
            <td class="px-4 py-3 text-right font-bold text-emerald-700 text-base" id="totalNet">
                UGX {{ number_format($payslips->sum('net_salary')) }}
            </td>
            <td></td>
        </tr>
        </tfoot>
    </table>
</div>

</form>

@push('scripts')
<script>
function toggleAll(master) {
    document.querySelectorAll('.slip-checkbox').forEach(cb => cb.checked = master.checked);
    updateCount();
}

function updateCount() {
    const all   = document.querySelectorAll('.slip-checkbox');
    const checked = document.querySelectorAll('.slip-checkbox:checked');
    document.getElementById('selectedCount').textContent = checked.length;
    document.getElementById('totalCount').textContent   = checked.length;

    // Recalculate total net
    let total = 0;
    checked.forEach(cb => {
        const row = cb.closest('tr');
        total += parseFloat(row.dataset.net || 0);
    });
    document.getElementById('totalNet').textContent = 'UGX ' + total.toLocaleString('en-UG', {maximumFractionDigits:0});

    // Visual: grey-out deselected rows
    all.forEach(cb => {
        const row = cb.closest('tr');
        if (cb.checked) {
            row.classList.remove('opacity-40','bg-red-50');
        } else {
            row.classList.add('opacity-40','bg-red-50');
        }
    });

    // Update select-all master state
    const master = document.getElementById('selectAll');
    master.indeterminate = checked.length > 0 && checked.length < all.length;
    master.checked = checked.length === all.length;
}

function confirmRelease() {
    const checked = document.querySelectorAll('.slip-checkbox:checked').length;
    const total   = document.querySelectorAll('.slip-checkbox').length;
    const withheld = total - checked;
    if (withheld > 0) {
        return confirm(`Release payment to ${checked} employees and withhold ${withheld} employees?\n\nMake sure you have entered a reason for withheld employees.`);
    }
    return confirm(`Release payment to all ${checked} employees?`);
}
</script>
@endpush

@endsection
