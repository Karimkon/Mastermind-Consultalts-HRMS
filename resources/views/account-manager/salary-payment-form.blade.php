@extends('layouts.app')
@php $editing = isset($payment); @endphp
@section('title', $editing ? 'Edit Salary Payment' : 'Record Salary Payment')
@section('content')

<x-page-header
    :title="$editing ? 'Edit Salary Payment' : 'Record Salary Payment'"
    :subtitle="$editing ? 'Update the salary breakdown for ' . $payment->employee?->full_name . ' — ' . $payment->period_label : 'Enter the salary breakdown for an employee'">
    <a href="{{ route('account-manager.salary-payments') }}" class="btn-secondary text-sm">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
</x-page-header>
<x-alert/>

<div class="max-w-3xl" id="salary-form-root"
     x-data="{
         basic: {{ $editing ? $payment->basic_salary : 0 }},
         allow: {{ $editing ? $payment->allowances : 0 }},
         paye:  {{ $editing ? $payment->paye_tax : 0 }},
         nssf:  {{ $editing ? $payment->nssf : 0 }},
         other: {{ $editing ? $payment->other_deductions : 0 }},
         salaryMap: @json($employeeSalaries ?? []),
         get gross()    { return parseFloat(this.basic||0) + parseFloat(this.allow||0); },
         get totalDed() { return parseFloat(this.paye||0) + parseFloat(this.nssf||0) + parseFloat(this.other||0); },
         get net()      { return this.gross - this.totalDed; },
         fmt(n) { return Math.round(n).toLocaleString('en-UG'); }
     }"
     @emp-selected.window="basic = salaryMap[$event.detail.empId] ?? basic">

    <form method="POST"
          action="{{ $editing ? route('account-manager.salary-payments.update', $payment) : route('account-manager.salary-payments.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- Employee + Period --}}
        <div class="card p-6 mb-5">
            <h3 class="text-base font-bold text-slate-700 mb-4 flex items-center gap-2">
                <i class="fas fa-user-tie text-blue-500"></i> Employee & Period
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($editing)
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Employee</label>
                    <p class="form-input bg-slate-50 text-slate-700 font-semibold">{{ $payment->employee?->full_name }}</p>
                    <input type="hidden" name="employee_id" value="{{ $payment->employee_id }}">
                    <input type="hidden" name="client_id"   value="{{ $payment->client_id }}">
                    <input type="hidden" name="period_month" value="{{ $payment->period_month }}">
                    <input type="hidden" name="period_year"  value="{{ $payment->period_year }}">
                    <p class="text-xs text-slate-400 mt-1">Period: {{ $payment->period_label }}</p>
                </div>
                @else
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Employee <span class="text-red-500">*</span></label>
                    <select name="employee_id" id="employee-select" class="form-input w-full" required>
                        <option value="">— Select Employee —</option>
                        @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>
                            {{ $emp->full_name }} ({{ $emp->emp_number }}) — {{ $emp->department?->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Client <span class="text-red-500">*</span></label>
                    <select name="client_id" id="client-select" class="form-input w-full" required>
                        <option value="">— Select Client —</option>
                        @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->company_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Month <span class="text-red-500">*</span></label>
                    <select name="period_month" class="form-input" required>
                        <option value="">— Month —</option>
                        @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" {{ old('period_month', date('n')) == $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Year <span class="text-red-500">*</span></label>
                    <select name="period_year" class="form-input" required>
                        @foreach(range(date('Y'), 2020) as $y)
                        <option value="{{ $y }}" {{ old('period_year', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>

        {{-- Earnings --}}
        <div class="card p-6 mb-5">
            <h3 class="text-base font-bold text-slate-700 mb-4 flex items-center gap-2">
                <i class="fas fa-plus-circle text-emerald-500"></i> Earnings
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Gross Salary (UGX) <span class="text-red-500">*</span></label>
                    <input type="number" name="basic_salary" x-model="basic" min="0" step="1"
                           value="{{ old('basic_salary', $editing ? $payment->basic_salary : '') }}"
                           class="form-input" placeholder="e.g. 800000" required>
                    <p class="text-xs text-slate-400 mt-1">Auto-filled from employee record</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Additional Allowances (UGX)</label>
                    <input type="number" name="allowances" x-model="allow" min="0" step="1"
                           value="{{ old('allowances', $editing ? $payment->allowances : 0) }}"
                           class="form-input" placeholder="Bonus, overtime, etc.">
                </div>
            </div>
            <div class="mt-3 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3 flex justify-between items-center">
                <span class="text-sm font-semibold text-emerald-700">Total Earnings</span>
                <span class="text-lg font-bold text-emerald-700" x-text="'UGX ' + fmt(gross)"></span>
            </div>
        </div>

        {{-- Deductions --}}
        <div class="card p-6 mb-5">
            <h3 class="text-base font-bold text-slate-700 mb-4 flex items-center gap-2">
                <i class="fas fa-minus-circle text-red-500"></i> Deductions
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">PAYE Tax (UGX)</label>
                    <input type="number" name="paye_tax" x-model="paye" min="0" step="1"
                           value="{{ old('paye_tax', $editing ? $payment->paye_tax : 0) }}"
                           class="form-input" placeholder="0">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">NSSF (UGX)</label>
                    <input type="number" name="nssf" x-model="nssf" min="0" step="1"
                           value="{{ old('nssf', $editing ? $payment->nssf : 0) }}"
                           class="form-input" placeholder="0">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Other Deductions (UGX)</label>
                    <input type="number" name="other_deductions" x-model="other" min="0" step="1"
                           value="{{ old('other_deductions', $editing ? $payment->other_deductions : 0) }}"
                           class="form-input" placeholder="Loan, advance, etc.">
                </div>
            </div>
            <div class="mt-3 bg-red-50 border border-red-200 rounded-lg px-4 py-3 flex justify-between items-center">
                <span class="text-sm font-semibold text-red-600">Total Deductions</span>
                <span class="text-lg font-bold text-red-600" x-text="'UGX ' + fmt(totalDed)"></span>
            </div>
        </div>

        {{-- Net Pay Summary --}}
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl px-6 py-5 mb-5 flex justify-between items-center">
            <div>
                <p class="text-blue-200 text-sm font-semibold uppercase tracking-wide">Net Pay</p>
                <p class="text-white text-xs mt-1">Gross − Deductions</p>
            </div>
            <span class="text-white text-3xl font-extrabold" x-text="'UGX ' + fmt(net)"></span>
        </div>

        {{-- Payment Details --}}
        <div class="card p-6 mb-5">
            <h3 class="text-base font-bold text-slate-700 mb-4 flex items-center gap-2">
                <i class="fas fa-credit-card text-purple-500"></i> Payment Details
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Payment Method</label>
                    <select name="payment_method" class="form-input">
                        <option value="">— Select —</option>
                        <option value="bank_transfer" {{ old('payment_method', $editing ? $payment->payment_method : '') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="mobile_money"  {{ old('payment_method', $editing ? $payment->payment_method : '') === 'mobile_money'  ? 'selected' : '' }}>Mobile Money</option>
                        <option value="cash"          {{ old('payment_method', $editing ? $payment->payment_method : '') === 'cash'          ? 'selected' : '' }}>Cash</option>
                        <option value="cheque"        {{ old('payment_method', $editing ? $payment->payment_method : '') === 'cheque'        ? 'selected' : '' }}>Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Reference / Receipt No.</label>
                    <input type="text" name="payment_reference" class="form-input"
                           value="{{ old('payment_reference', $editing ? $payment->payment_reference : '') }}"
                           placeholder="e.g. TXN-20260601-001">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Payment Date</label>
                    <input type="date" name="payment_date" class="form-input"
                           value="{{ old('payment_date', $editing ? $payment->payment_date?->format('Y-m-d') : today()->format('Y-m-d')) }}">
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Notes</label>
                <textarea name="notes" rows="2" class="form-input" placeholder="Any additional notes...">{{ old('notes', $editing ? $payment->notes : '') }}</textarea>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">
                <i class="fas fa-save mr-1"></i> {{ $editing ? 'Update Payment' : 'Save Payment' }}
            </button>
            <a href="{{ route('account-manager.salary-payments') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#employee-select').select2({
        theme: 'classic',
        placeholder: '— Select Employee —',
        allowClear: true,
        width: '100%'
    });

    $('#client-select').select2({
        theme: 'classic',
        placeholder: '— Select Client —',
        allowClear: true,
        width: '100%'
    });

    // Bridge Select2 jQuery change → Alpine via window custom event
    $('#employee-select').on('change', function () {
        window.dispatchEvent(new CustomEvent('emp-selected', {
            detail: { empId: $(this).val() }
        }));
    });

    // Re-select old value after validation failure
    @if(old('employee_id'))
        $('#employee-select').val('{{ old('employee_id') }}').trigger('change.select2');
    @endif
    @if(old('client_id'))
        $('#client-select').val('{{ old('client_id') }}').trigger('change.select2');
    @endif
});
</script>
@endpush
