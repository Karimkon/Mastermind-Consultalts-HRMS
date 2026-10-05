{{-- One payslip's deduction, part by part, then the total.

     The parts add up to the total exactly: Other carries whatever is not one
     of the named statutory lines, so the row always reconciles.

     No @php here, inline or block. An inline @php(...) inside this loop made
     Blade swallow the @endforeach after it and the compiled partial ended
     mid-statement -- the source looks balanced and view:cache still reports
     success, so it is only visible by linting the compiled output. --}}
@foreach($parts as $code => $label)
<td class="px-4 py-2.5 text-right text-slate-500">{{ $slip->deductionPart($code) ? number_format($slip->deductionPart($code)) : '—' }}</td>
@endforeach
<td class="px-4 py-2.5 text-right text-slate-500">{{ $slip->otherDeductions() ? number_format($slip->otherDeductions()) : '—' }}</td>
<td class="px-4 py-2.5 text-right text-orange-600 font-medium">{{ number_format($slip->total_deductions) }}</td>
