{{-- The deduction broken into its parts, before the total it makes.

     Shared by the run page and by HR, Finance and MD review, so none of the
     four can drift apart: the same run shows the same columns wherever it is
     looked at.

     $parts comes from Payslip::partsInUse(), so WHT and LST appear only on
     runs that carry them -- a column of dashes reads as a figure somebody
     checked, which is worse than no column.

     $headClass lets a page keep its own heading style; the run page uses
     .table-head. No @php here, inline or block: an inline one inside a
     partial like this has twice now made Blade swallow the directive after
     it, leaving a compiled view that ends mid-statement while view:cache
     still reports success. --}}
@foreach($parts as $label)
<th class="{{ $headClass ?? 'px-4 py-3 text-right font-normal text-slate-500' }}">{{ $label }}</th>
@endforeach
<th class="{{ $headClass ?? 'px-4 py-3 text-right font-normal text-slate-500' }}">Other</th>
<th class="{{ $headClass ?? 'px-4 py-3 text-right font-normal text-slate-500' }}">Deductions</th>
