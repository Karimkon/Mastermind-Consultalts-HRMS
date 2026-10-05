{{-- The approval table, shared by HR, Finance and MD review.

     The three screens were separate copies of the same markup and had already
     drifted: HR had a total under every money column and the other two had
     only a net total, so Finance and MD could not see what the PAYE or NSSF
     bill came to for the people they were approving. Whoever is approving, it
     is the same payroll and the same question, so it is now one table.

     What legitimately differs between the stages stays outside this file: the
     heading, the banner, the form action and the approve button. What arrives
     here is only the accent colour and what the status badge should say.

     Expects:
       $payslips   the run's payslips
       $accent     tailwind colour name for the tick boxes (blue/purple/green)
       $clearedAs  what an un-withheld row says it has already passed --}}
@php
    // Which deduction parts this run actually carries. WHT and LST appear
    // only where there is something to show.
    $parts = \App\Models\Payslip::partsInUse($payslips);

    // 6 fixed columns before the deductions block (tick, employee,
    // department, designation, basic, gross).
    $leadingCols = 6;
@endphp
<div class="card overflow-x-auto">
    <table class="w-full text-sm" id="employeeTable">
        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
        <tr>
            <th class="px-4 py-3 text-left w-8"></th>
            <th class="px-4 py-3 text-left">Employee</th>
            <th class="px-4 py-3 text-left">Department</th>
            <th class="px-4 py-3 text-left">Designation</th>
            <th class="px-4 py-3 text-right">Basic</th>
            <th class="px-4 py-3 text-right">Gross</th>
            @include('payroll.partials.deduction-heads', ['parts' => $parts])
            <th class="px-4 py-3 text-right font-bold text-emerald-700">Net Pay</th>
            <th class="px-4 py-3 text-center">Days</th>
            <th class="px-4 py-3 text-center">Status</th>
        </tr>
        </thead>

        <tbody class="divide-y divide-slate-100" id="tableBody">
        @foreach($payslips as $slip)
        @php
            $isWithheld = $slip->payment_status === 'withheld';
        @endphp
        <tr class="employee-row hover:bg-slate-50 transition-colors {{ $isWithheld ? 'opacity-40 bg-red-50' : '' }}"
            id="row-{{ $slip->id }}"
            data-name="{{ strtolower($slip->employee->full_name) }}"
            data-emp="{{ strtolower($slip->employee->emp_number) }}"
            data-dept="{{ $slip->employee->department?->name }}"
            data-basic="{{ $slip->basic_salary }}"
            data-gross="{{ $slip->gross_salary }}"
            @foreach($parts as $code => $label)
            data-{{ strtolower(str_replace('_', '', $code)) }}="{{ $slip->deductionPart($code) }}"
            @endforeach
            data-other="{{ $slip->otherDeductions() }}"
            data-ded="{{ $slip->total_deductions }}"
            data-net="{{ $slip->net_salary }}">
            <td class="px-4 py-2.5">
                <input type="checkbox" name="selected_payslips[]" value="{{ $slip->id }}"
                       {{ !$isWithheld ? 'checked' : '' }} onchange="updateCount()"
                       class="w-4 h-4 rounded text-{{ $accent }}-600 border-slate-300 slip-checkbox">
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
            @include('payroll.partials.deduction-cells', ['slip' => $slip, 'parts' => $parts])
            <td class="px-4 py-2.5 text-right font-bold text-emerald-700">{{ number_format($slip->net_salary) }}</td>
            <td class="px-4 py-2.5 text-center text-xs">
                <span class="text-green-600">{{ $slip->worked_days }}P</span> /
                <span class="text-red-400">{{ $slip->absent_days }}A</span>
            </td>
            <td class="px-4 py-2.5 text-center">
                @if($isWithheld)
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-red-100 text-red-700">
                        Withheld{{ $slip->withheld_stage ? ' by '.strtoupper($slip->withheld_stage) : '' }}
                    </span>
                @else
                    <span class="text-xs px-2 py-0.5 rounded-full bg-{{ $accent }}-100 text-{{ $accent }}-700">{{ $clearedAs }}</span>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>

        <tfoot class="bg-slate-50 border-t-2 border-slate-300">
        <tr>
            <td colspan="{{ $leadingCols - 2 }}" class="px-4 py-3 text-sm font-semibold text-slate-600">
                Approving payment for
                <span id="footerCount" class="text-{{ $accent }}-700">{{ $payslips->where('payment_status','pending')->count() }}</span>
                employees
                @php
                    $withheldPrev = $payslips->where('payment_status','withheld')->count();
                @endphp
                @if($withheldPrev)
                &nbsp;<span class="text-red-500 text-xs">({{ $withheldPrev }} already withheld)</span>
                @endif
            </td>
            {{-- A total under every money column, and all of them move together
                 when somebody is withheld. A static total beside a live one is
                 worse than no total at all. --}}
            <td class="px-4 py-3 text-right text-sm font-semibold text-slate-600" id="foot-basic">{{ number_format($payslips->sum('basic_salary')) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-slate-600" id="foot-gross">{{ number_format($payslips->sum('gross_salary')) }}</td>
            @foreach($parts as $code => $label)
            <td class="px-4 py-3 text-right text-sm font-semibold text-slate-600"
                id="foot-{{ strtolower(str_replace('_', '', $code)) }}">{{ number_format($payslips->sum(fn ($s) => $s->deductionPart($code))) }}</td>
            @endforeach
            <td class="px-4 py-3 text-right text-sm font-semibold text-slate-600" id="foot-other">{{ number_format($payslips->sum(fn ($s) => $s->otherDeductions())) }}</td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-orange-600" id="foot-ded">{{ number_format($payslips->sum('total_deductions')) }}</td>
            <td class="px-4 py-3 text-right font-bold text-emerald-700 text-base" id="footerNet">
                UGX {{ number_format($payslips->sum('net_salary')) }}
            </td>
            <td colspan="2"></td>
        </tr>
        </tfoot>
    </table>
</div>

{{-- The codes the totals script re-sums, in column order. Rendered here
     because only this file knows which parts the run turned out to have. --}}
<script>
    window.reviewTotalCols = ['basic', 'gross',
        @foreach($parts as $code => $label)'{{ strtolower(str_replace('_', '', $code)) }}', @endforeach
        'other', 'ded', 'net'];
</script>
