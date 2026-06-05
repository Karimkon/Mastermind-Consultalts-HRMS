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
        <p class="text-sm mt-0.5">All employees are selected by default. <strong>Uncheck</strong> any employee whose payment should be withheld this cycle — e.g. disciplinary case, dispute, or absence without leave. Enter a reason for withheld employees, then click <strong>"Approve &amp; Submit to Finance"</strong>.</p>
    </div>
</div>

<form method="POST" action="{{ route('payroll.hr-approve', $payroll) }}" id="hrReviewForm">
@csrf

{{-- Top action bar --}}
<div class="card p-4 mb-4">
    <div class="flex flex-wrap items-center gap-4 justify-between">
        {{-- Select all + counter --}}
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer select-none">
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

        {{-- Withheld reason + submit --}}
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs text-slate-500 block mb-0.5">Reason for withheld employees</label>
                <input type="text" name="withheld_reason"
                       placeholder="e.g. Disciplinary action pending investigation"
                       class="form-input text-sm py-1.5 w-80">
            </div>
            <button type="submit" onclick="return confirmApprove()"
                    class="btn-primary flex items-center gap-2 whitespace-nowrap">
                <i class="fas fa-check-double"></i> Approve &amp; Submit to Finance
            </button>
        </div>
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
<div class="card overflow-hidden">
    <table class="w-full text-sm" id="employeeTable">
        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
        <tr>
            <th class="px-4 py-3 text-left w-8"></th>
            <th class="px-4 py-3 text-left">Employee</th>
            <th class="px-4 py-3 text-left">Department</th>
            <th class="px-4 py-3 text-left">Role</th>
            <th class="px-4 py-3 text-right">Basic</th>
            <th class="px-4 py-3 text-right">Gross</th>
            <th class="px-4 py-3 text-right">Deductions</th>
            <th class="px-4 py-3 text-right font-bold text-emerald-700">Net Pay</th>
            <th class="px-4 py-3 text-center">Days</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100" id="tableBody">
        @foreach($payslips as $slip)
        <tr class="employee-row hover:bg-slate-50 transition-colors"
            id="row-{{ $slip->id }}"
            data-name="{{ strtolower($slip->employee->full_name) }}"
            data-emp="{{ strtolower($slip->employee->emp_number) }}"
            data-dept="{{ $slip->employee->department?->name }}"
            data-net="{{ $slip->net_salary }}">
            <td class="px-4 py-2.5">
                <input type="checkbox" name="selected_payslips[]" value="{{ $slip->id }}"
                       checked onchange="updateCount()"
                       class="w-4 h-4 rounded text-blue-600 border-slate-300 slip-checkbox">
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center gap-2">
                    <img src="{{ $slip->employee->avatar_url }}" class="w-7 h-7 rounded-full object-cover flex-shrink-0">
                    <div>
                        <p class="font-medium text-slate-800 leading-tight">{{ $slip->employee->full_name }}</p>
                        <p class="text-xs text-slate-400">{{ $slip->employee->emp_number }}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $slip->employee->department?->name ?? '—' }}</td>
            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $slip->employee->designation?->title ?? '—' }}</td>
            <td class="px-4 py-2.5 text-right text-slate-600">{{ number_format($slip->basic_salary) }}</td>
            <td class="px-4 py-2.5 text-right text-slate-700">{{ number_format($slip->gross_salary) }}</td>
            <td class="px-4 py-2.5 text-right text-orange-600">{{ number_format($slip->total_deductions) }}</td>
            <td class="px-4 py-2.5 text-right font-bold text-emerald-700">{{ number_format($slip->net_salary) }}</td>
            <td class="px-4 py-2.5 text-center text-xs">
                <span class="text-green-600">{{ $slip->worked_days }}P</span> /
                <span class="text-red-400">{{ $slip->absent_days }}A</span>
            </td>
        </tr>
        @endforeach
        </tbody>
        <tfoot class="bg-slate-50 border-t-2 border-slate-300">
        <tr>
            <td colspan="7" class="px-4 py-3 text-sm font-semibold text-slate-600">
                Approving payment for <span id="footerCount" class="text-blue-700">{{ $payslips->count() }}</span> employees
                @php $withheldPrev = $payslips->where('payment_status','withheld')->count(); @endphp
                @if($withheldPrev)
                &nbsp;<span class="text-red-500 text-xs">({{ $withheldPrev }} previously withheld)</span>
                @endif
            </td>
            <td class="px-4 py-3 text-right font-bold text-emerald-700 text-base" id="footerNet">
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
const allNet = {{ $payslips->sum('net_salary') }};

function toggleAll(master) {
    document.querySelectorAll('.slip-checkbox').forEach(cb => cb.checked = master.checked);
    updateCount();
}

function updateCount() {
    const all     = document.querySelectorAll('.slip-checkbox');
    const checked = [...document.querySelectorAll('.slip-checkbox:checked')];

    document.getElementById('selectedCount').textContent = checked.length;
    document.getElementById('footerCount').textContent   = checked.length;

    let total = 0;
    checked.forEach(cb => { total += parseFloat(cb.closest('tr').dataset.net || 0); });
    const fmt = 'UGX ' + Math.round(total).toLocaleString();
    document.getElementById('totalNet').textContent  = fmt;
    document.getElementById('footerNet').textContent = fmt;

    // Visual feedback
    all.forEach(cb => {
        const row = cb.closest('tr');
        row.classList.toggle('opacity-40', !cb.checked);
        row.classList.toggle('bg-red-50',  !cb.checked);
        row.classList.toggle('hover:bg-red-50', !cb.checked);
    });

    // Master checkbox state
    const master = document.getElementById('selectAll');
    master.indeterminate = checked.length > 0 && checked.length < all.length;
    master.checked = checked.length === all.length;
}

function filterTable(val) {
    const name = document.getElementById('searchInput').value.toLowerCase();
    const dept = document.getElementById('deptFilter').value;
    document.querySelectorAll('.employee-row').forEach(row => {
        const matchName = !name || row.dataset.name.includes(name) || row.dataset.emp.includes(name);
        const matchDept = !dept || row.dataset.dept === dept;
        row.style.display = (matchName && matchDept) ? '' : 'none';
    });
}

function showOnlyDeselected() {
    document.querySelectorAll('.employee-row').forEach(row => {
        const cb = row.querySelector('.slip-checkbox');
        row.style.display = cb.checked ? 'none' : '';
    });
}

function showAll() {
    document.getElementById('searchInput').value = '';
    document.getElementById('deptFilter').value  = '';
    document.querySelectorAll('.employee-row').forEach(row => row.style.display = '');
}

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
