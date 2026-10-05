{{-- The deduction broken into its parts, before the total it makes.

     Shared by HR, Finance and MD review so the three cannot drift apart:
     the same reviewer sees the same columns at every stage.

     $parts comes from Payslip::partsInUse(), so WHT and LST appear only on
     runs that carry them -- a column of dashes reads as a figure somebody
     checked, which is worse than no column. --}}
@foreach($parts as $label)
<th class="px-4 py-3 text-right font-normal text-slate-500">{{ $label }}</th>
@endforeach
<th class="px-4 py-3 text-right font-normal text-slate-500">Other</th>
<th class="px-4 py-3 text-right">Deductions</th>
