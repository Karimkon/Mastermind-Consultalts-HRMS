@extends("layouts.app")
@section("title", $run->title)
@section("content")

<x-page-header :title="$run->title" subtitle="Payslip breakdown for this payroll run">
    <a href="{{ route('account-manager.payroll') }}" class="btn-secondary text-sm">
        <i class="fas fa-arrow-left mr-1"></i> Back to Payroll
    </a>
</x-page-header>
<x-alert/>

{{-- Run summary --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ $payslips->count() }}</p>
        <p class="text-xs text-slate-500 mt-1">Employees</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-slate-700">{{ number_format($payslips->sum('gross_salary')) }}</p>
        <p class="text-xs text-slate-500 mt-1">Gross (UGX)</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-amber-600">{{ number_format($payslips->sum('tax_amount')) }}</p>
        <p class="text-xs text-slate-500 mt-1">PAYE Tax (UGX)</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-lg font-bold text-red-500">{{ number_format($payslips->sum('total_deductions')) }}</p>
        <p class="text-xs text-slate-500 mt-1">Deductions (UGX)</p>
    </div>
    <div class="card p-4 text-center border-l-4 border-emerald-500">
        <p class="text-lg font-bold text-emerald-700">{{ number_format($payslips->sum('net_salary')) }}</p>
        <p class="text-xs text-slate-500 mt-1">Net Pay (UGX)</p>
    </div>
</div>

{{-- Status + Lock banner --}}
<div class="mb-5 flex flex-col gap-3">
    <div class="flex items-center gap-3">
        @if($run->status === 'paid')
        <div class="flex-1 bg-emerald-50 border border-emerald-300 rounded-xl px-4 py-3 flex items-center gap-3">
            <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
            <div>
                <p class="font-semibold text-emerald-700">Payment Completed</p>
                <p class="text-xs text-emerald-600">
                    Paid on {{ $run->paid_at?->format('d M Y H:i') }} via
                    <strong>{{ str_replace('_', ' ', ucwords($run->payment_method ?? '—')) }}</strong>
                    @if($run->payment_reference) · Ref: <strong>{{ $run->payment_reference }}</strong>@endif
                </p>
            </div>
        </div>
        @elseif(in_array($run->status, ['processed', 'approved']))
        <div class="flex-1 bg-emerald-50 border border-emerald-300 rounded-xl px-4 py-3 flex items-center gap-3">
            <i class="fas fa-money-bill-wave text-emerald-500 text-xl"></i>
            <div>
                <p class="font-semibold text-emerald-700">Ready for Payment</p>
                <p class="text-xs text-emerald-600">
                    Payroll is processed and ready. Use the <strong>Mark as Paid</strong> form below once salaries have been disbursed.
                </p>
            </div>
        </div>
        @else
        <div class="flex-1 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex items-center gap-3">
            <i class="fas fa-clock text-blue-400 text-xl"></i>
            <div>
                <p class="font-semibold text-blue-700">Status: {{ ucfirst($run->status) }}</p>
                <p class="text-xs text-blue-500">This payroll run is still being prepared.</p>
            </div>
        </div>
        @endif

        @if($run->payment_date && $run->status !== 'paid')
        <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-center min-w-[140px]">
            <p class="text-xs text-slate-500 font-semibold uppercase">Pay Date</p>
            <p class="font-bold text-slate-800">{{ $run->payment_date->format('d M Y') }}</p>
        </div>
        @endif
    </div>

    {{-- Mark as Paid form — show when approved/processed --}}
    @if(in_array($run->status, ['approved', 'processed']))
    <div class="bg-white border border-emerald-200 rounded-xl p-5" x-data="{ open: false }">
        <button @click="open = !open"
                class="flex items-center gap-2 text-emerald-700 font-semibold text-sm hover:text-emerald-900 transition">
            <i class="fas fa-money-bill-wave text-emerald-500"></i>
            Mark as Paid
            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>
        <div x-show="open" x-transition class="mt-4">
            <form method="POST" action="{{ route('account-manager.payroll.mark-paid', $run) }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Payment Method <span class="text-red-500">*</span></label>
                        <select name="payment_method" class="form-input" required>
                            <option value="">— Select —</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Payment Reference</label>
                        <input type="text" name="payment_reference" class="form-input" placeholder="e.g. TXN-20260325-001">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Payment Date</label>
                        <input type="date" name="payment_date" class="form-input" value="{{ today()->format('Y-m-d') }}">
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="btn-primary"
                            onclick="return confirm('Confirm marking this payroll as PAID? This cannot be undone.')">
                        <i class="fas fa-check mr-1"></i> Confirm Payment
                    </button>
                    <p class="text-xs text-slate-500">This sets the run status to <strong>Paid</strong>. This action cannot be undone.</p>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

{{-- Payslips table --}}
<x-data-table>
    <thead class="bg-slate-50">
        <tr>
            <th class="table-head px-6 py-3 text-left">Employee</th>
            <th class="table-head px-4 py-3 text-left">Department</th>
            <th class="table-head px-4 py-3 text-right">Basic Salary</th>
            <th class="table-head px-4 py-3 text-right">Allowances</th>
            <th class="table-head px-4 py-3 text-right">Gross</th>
            <th class="table-head px-4 py-3 text-right">PAYE Tax</th>
            <th class="table-head px-4 py-3 text-right">Deductions</th>
            <th class="table-head px-4 py-3 text-right font-bold text-emerald-700">Net Pay</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
        @forelse($payslips as $p)
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-3">
                <p class="font-semibold text-slate-800">{{ $p->employee?->full_name ?? '—' }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $p->employee?->department?->name ?? '—' }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ number_format($p->basic_salary) }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ number_format($p->total_allowances) }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ number_format($p->gross_salary) }}</td>
            <td class="px-4 py-3 text-right font-mono text-amber-600">{{ number_format($p->tax_amount) }}</td>
            <td class="px-4 py-3 text-right font-mono text-red-500">{{ number_format($p->total_deductions) }}</td>
            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700">{{ number_format($p->net_salary) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" class="px-6 py-12 text-center text-slate-400">No payslips in this run.</td>
        </tr>
        @endforelse
    </tbody>
    @if($payslips->count())
    <tfoot class="bg-slate-100 font-bold text-sm">
        <tr>
            <td colspan="{{ 2 }}" class="px-6 py-3 text-slate-700">TOTALS</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ number_format($payslips->sum('basic_salary')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ number_format($payslips->sum('total_allowances')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ number_format($payslips->sum('gross_salary')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-amber-700">{{ number_format($payslips->sum('tax_amount')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-red-600">{{ number_format($payslips->sum('total_deductions')) }}</td>
            <td class="px-4 py-3 text-right font-mono text-emerald-800">{{ number_format($payslips->sum('net_salary')) }}</td>
        </tr>
    </tfoot>
    @endif
</x-data-table>

@endsection
