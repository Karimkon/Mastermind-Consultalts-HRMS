<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
/* One family for the whole slip. DejaVu Sans ships only 400 and 700, so any
   intermediate weight (600) makes DomPDF fall back to a serif face — which is
   why the employee name used to render in a different font from the table
   below it. Every rule here sticks to 400 or 700. */
* { margin:0; padding:0; box-sizing:border-box;
    font-family:"DejaVu Sans", Arial, sans-serif; }
/* Type is set larger than a typical payslip: these are read on phones and
   printed for staff on site, and removing the employer-contributions block
   freed the vertical space to do it while still fitting one A4 page. */
body { font-family:"DejaVu Sans", Arial, sans-serif; font-size:12.5px; color:#111; background:#fff; }
.page { padding:24px 28px; }

table { border-collapse:collapse; }

/* ── Header ── */
.hdr { width:100%; }
.hdr td { vertical-align:middle; padding:0; }
.co-name { font-size:19px; font-weight:700; color:#ea580c; letter-spacing:.02em; }
.co-addr { font-size:10px; color:#555; line-height:1.6; margin-top:4px; }
.slip-badge { background:#ea580c; color:#fff; font-size:15px; font-weight:700;
              padding:10px 20px; text-align:center; letter-spacing:.08em; }

/* ── Orange divider ── */
.rule { border:none; border-top:2.5px solid #ea580c; margin:10px 0; }

/* ── Employee info row ── */
.info { width:100%; margin-bottom:13px; }
.info th { background:#ea580c; color:#fff; font-size:9.5px; font-weight:700;
           text-transform:uppercase; letter-spacing:.04em; padding:6px 5px;
           text-align:center; border:1px solid #c2410c; }
.info td { padding:7px 5px; text-align:center; font-size:12px; font-weight:400;
           border:1px solid #d1d5db; background:#fff; }

/* ── Main earnings/deductions/balances table ── */
.mt { width:100%; margin-bottom:13px; }
.mt th { background:#ea580c; color:#fff; font-size:10.5px; font-weight:700;
         text-transform:uppercase; letter-spacing:.03em; padding:7px 8px;
         border:1px solid #c2410c; text-align:left; }
.mt th.r { text-align:right; }
.mt td { padding:6px 8px; border:1px solid #e5e7eb; font-size:12px; vertical-align:middle; }
.mt td.r { text-align:right; }
/* The balances column is reference information, so it stays regular weight.
   The only bold figures on the slip are the two totals below and the net pay
   band, which is what the eye should land on. */
.mt td.bal { font-weight:400; font-size:12px; color:#374151; background:#f9fafb; }
.mt td.bval { font-weight:400; text-align:right; background:#f9fafb; font-size:12px; }
.mt tr.totrow td { background:#f3f4f6; font-weight:700; border-top:1.5px solid #ea580c; font-size:12.5px; }
/* Blank spacer between the two totals — no fill and no side rules, so it reads
   as a genuine gap. It keeps its top rule so the deductions block above still
   closes off cleanly. */
.mt tr.totrow td.gap { background:#fff; border:none; border-top:1.5px solid #ea580c; }

/* ── Orange block outlines ──
   Each pair of columns (earned income, deductions, balances) is boxed in
   orange so the three read as separate panels rather than one wide grid.
   gl = left edge of a block, gr = right edge. */
.info th.gl, .info td.gl,
.mt   th.gl, .mt   td.gl,
.bank th.gl, .bank td.gl { border-left:1.5px solid #ea580c; }

.info th.gr, .info td.gr,
.mt   th.gr, .mt   td.gr,
.bank th.gr, .bank td.gr { border-right:1.5px solid #ea580c; }

/* Close the single-row tables along the bottom. */
.info tbody td, .bank tbody td { border-bottom:1.5px solid #ea580c; }

/* Close the two totals blocks, leaving the gap between them open. */
.mt tr.totrow td.gl, .mt tr.totrow td.gr { border-bottom:1.5px solid #ea580c; }

/* ── Net pay band ── */
.remit { width:100%; margin-bottom:13px; }
.remit td { padding:11px 12px; }
.remit td.lbl { background:#111827; color:#fff; font-weight:700; font-size:14px;
                letter-spacing:.04em; width:70%; }
.remit td.amt { background:#ea580c; color:#fff; font-weight:700; font-size:19px;
                text-align:right; }

/* ── Payment details ── */
.bank { width:100%; margin-bottom:0; }
.bank th { background:#ea580c; color:#fff; font-size:10.5px; font-weight:700;
           text-transform:uppercase; letter-spacing:.03em; padding:7px 8px;
           border:1px solid #c2410c; text-align:center; }
.bank td { padding:9px 8px; border:1px solid #e5e7eb; font-size:12.5px;
           font-weight:400; text-align:center; }

/* ── Footer ── */
.footer { margin-top:16px; padding-top:7px; border-top:1px solid #e5e7eb;
          font-size:9px; color:#9ca3af; text-align:center; }
</style>
</head>
<body>
<div class="page">

@php
  $emp     = $payslip->employee;
  $period  = date('F Y', mktime(0, 0, 0, $payroll_run->month, 1, $payroll_run->year));
  $payDate = $payroll_run->payment_date
             ? \Carbon\Carbon::parse($payroll_run->payment_date)->format('d M Y')
             : '—';

  // Deployment label: "Roofings Banda" = first word of client name + dept name
  $client     = $payroll_run->client ?? null;
  $deptName   = $emp->department?->name ?? '';
  if ($client) {
      $firstWord  = explode(' ', trim($client->company_name))[0];
      $deployment = trim($firstWord . ($deptName ? ' ' . $deptName : ''));
  } else {
      $deployment = $deptName ?: '—';
  }

  // Component lists — orderedComponents() puts PAYE before NSSF
  $allComps = collect($payslip->orderedComponents());
  $earns    = $allComps->where('type', 'allowance')->values();
  $deds     = $allComps->where('type', 'deduction')->values();

  $employeeNssf = $payslip->employeeNssf();
  $employerNssf = $payslip->employerNssf();
  $totalNssf    = $payslip->totalNssf();

  // The employer's 10% NSSF is shown on both sides of the slip: added to earned
  // income so the employee sees the full cost of employing them, then taken off
  // again under deductions because it is remitted straight to NSSF. The two
  // entries cancel, so net pay is exactly what it was before.
  $earnRows = [['Gross Pay', $payslip->gross_salary]];
  foreach ($earns as $e) $earnRows[] = [$e['name'], $e['amount']];
  if ($employerNssf > 0) $earnRows[] = ['NSSF (Employer 10%)', $employerNssf];

  $dedRows = [];
  foreach ($deds as $d) $dedRows[] = [$d['name'], $d['amount']];
  if ($employerNssf > 0) $dedRows[] = ['NSSF (Employer 10%)', $employerNssf];

  $totalEarned     = $payslip->gross_salary + $employerNssf;
  $totalDeductions = $payslip->total_deductions + $employerNssf;

  $balRows = [
      // Taxable income stays the employee's gross — the employer's share is
      // never part of the employee's taxable pay.
      ['TAXABLE INCOME',   $payslip->gross_salary],
      ['TOTAL DEDUCTIONS', $totalDeductions],
      ['NET PAY',          $payslip->net_salary],
  ];

  $rowCount = max(count($earnRows), count($dedRows), count($balRows));
@endphp

{{-- ── Header: Logo | Company + Title | Employee Photo ── --}}
<table class="hdr">
<tr>
  {{-- Logo --}}
  <td style="width:200px; vertical-align:middle;">
    @if(!empty($logo))
      <img src="{{ $logo }}" style="width:190px; height:auto;" alt="Mastermind Logo">
    @else
      <div style="font-size:30px; font-weight:700; color:#ea580c;">MM</div>
    @endif
  </td>
  {{-- Company info + centered title --}}
  <td style="vertical-align:middle; text-align:center; padding:0 8px;">
    <div class="co-name">MASTERMIND CONSULT LIMITED</div>
    <div class="co-addr">
      Plot 28A Katula Road, Kisasi, P.O. Box 74915, Kampala-Uganda<br>
      Tel: +256 393 215 289 &nbsp;&nbsp; www.mastermindconsults.co.ug<br>
      payroll@mastermindconsults.co.ug
    </div>
    <div style="margin-top:7px;">
      <div class="slip-badge" style="display:inline-block; padding:7px 22px;">PAYMENT ADVISE SLIP</div>
    </div>
  </td>
  {{-- Employee Photo --}}
  <td style="width:115px; vertical-align:middle; text-align:center;">
    @if(!empty($avatar))
      <img src="{{ $avatar }}" style="width:105px; height:105px; object-fit:cover; border-radius:4px; border:2px solid #ea580c;" alt="Photo">
    @else
      {{-- Placeholder sized to match a real photo, so the header keeps the same
           shape whether or not the employee has one on file. --}}
      <div style="width:105px; height:105px; background:#f3f4f6; border:2px solid #ea580c; border-radius:4px; text-align:center;">
        <span style="font-size:42px; line-height:105px; color:#d1d5db;">&#9786;</span>
      </div>
    @endif
    <div style="font-size:10.5px; font-weight:700; color:#6b7280; margin-top:5px;">{{ $payslip->employee->emp_number }}</div>
  </td>
</tr>
</table>

<hr class="rule">

{{-- ── Employee Info Row ── --}}
<table class="info">
<thead>
<tr>
  <th class="gl" style="width:22%">NAME</th>
  <th style="width:13%">ROLE</th>
  <th style="width:13%">DEPLOYMENT</th>
  <th style="width:11%">PERIOD</th>
  <th style="width:11%">PAY DATE</th>
  <th style="width:10%">PAY FREQ</th>
  <th style="width:10%">EMP NO</th>
  <th class="gr" style="width:10%">DAYS WORKED</th>
</tr>
</thead>
<tbody>
<tr>
  <td class="gl">{{ $emp->full_name }}</td>
  <td>{{ $emp->designation?->title ?? '—' }}</td>
  <td>{{ $deployment }}</td>
  <td>{{ $period }}</td>
  <td>{{ $payDate }}</td>
  <td>Monthly</td>
  <td>{{ $emp->emp_number }}</td>
  <td class="gr">{{ $payslip->worked_days }}</td>
</tr>
</tbody>
</table>

{{-- ── Main Earnings / Deductions / Balances Table ── --}}
<table class="mt">
<thead>
<tr>
  <th class="gl" style="width:22%">EARNED INCOME</th>
  <th class="r gr" style="width:12%">AMOUNT (UGX)</th>
  <th class="gl" style="width:22%">DEDUCTIONS</th>
  <th class="r gr" style="width:12%">AMOUNT (UGX)</th>
  <th class="gl" style="width:18%">BALANCES</th>
  <th class="r gr" style="width:14%">NET SALARY (UGX)</th>
</tr>
</thead>
<tbody>
@for($i = 0; $i < $rowCount; $i++)
<tr>
  @if(isset($earnRows[$i]))
    <td class="gl">{{ $earnRows[$i][0] }}</td>
    <td class="r gr">{{ number_format($earnRows[$i][1]) }}</td>
  @else
    <td class="gl"></td><td class="gr"></td>
  @endif
  @if(isset($dedRows[$i]))
    <td class="gl">{{ $dedRows[$i][0] }}</td>
    <td class="r gr">{{ number_format($dedRows[$i][1]) }}</td>
  @else
    <td class="gl"></td><td class="gr"></td>
  @endif
  @if(isset($balRows[$i]))
    <td class="bal gl">{{ $balRows[$i][0] }}</td>
    <td class="bval gr">{{ number_format($balRows[$i][1]) }}</td>
  @else
    <td class="bal gl"></td><td class="bval gr"></td>
  @endif
</tr>
@endfor
{{-- The two totals sit at opposite ends with an empty gap between them, so
     earned income reads under its own columns and deductions lands on the
     right beside the balances. --}}
<tr class="totrow">
  <td class="gl">TOTAL EARNED INCOME</td>
  <td class="r gr">{{ number_format($totalEarned) }}</td>
  <td class="gap"></td>
  <td class="gap"></td>
  <td class="gl">TOTAL DEDUCTIONS</td>
  <td class="r gr">{{ number_format($totalDeductions) }}</td>
</tr>
</tbody>
</table>

{{-- ── Net Pay ── --}}
<table class="remit">
<tr>
  <td class="lbl">NET PAY</td>
  <td class="amt">UGX &nbsp; {{ number_format($payslip->net_salary) }}</td>
</tr>
</table>

{{-- ── How this employee is paid ──
     Driven by paymentChannel(), so the slip shows mobile money details for a
     mobile money employee and bank details for a bank employee, rather than a
     fixed set of bank columns that read as blank for most casual staff. --}}
@php
  $channel = $emp->paymentChannel();

  [$providerLabel, $providerValue, $refLabel, $refValue] = match ($channel) {
      'mtn', 'airtel' => [
          'MOBILE MONEY NETWORK',
          strtoupper($channel) . ' Mobile Money',
          'MOBILE MONEY NUMBER',
          $emp->mobile_money_number ?: ($emp->payoutNumber() ?: '—'),
      ],
      'cash'   => ['PAYMENT METHOD', 'Cash',   'REFERENCE', '—'],
      'cheque' => ['PAYMENT METHOD', 'Cheque', 'REFERENCE', '—'],
      default  => [
          'BANK NAME',
          $emp->bank_name ?: '—',
          'BANK ACCOUNT NUMBER',
          $emp->bank_account ?: '—',
      ],
  };
@endphp
<table class="bank">
<thead>
<tr>
  <th class="gl" style="width:33%">{{ $providerLabel }}</th>
  <th style="width:34%">ACCOUNT NAME</th>
  <th class="gr" style="width:33%">{{ $refLabel }}</th>
</tr>
</thead>
<tbody>
<tr>
  <td class="gl">{{ $providerValue }}</td>
  <td>{{ $emp->full_name }}</td>
  <td class="gr">{{ $refValue }}</td>
</tr>
</tbody>
</table>

<div class="footer">
  Computer-generated payslip &mdash; no signature required &nbsp;&middot;&nbsp;
  Generated: {{ now()->format('d M Y H:i') }} &nbsp;&middot;&nbsp; Mastermind Consult Ltd
</div>

</div>
</body>
</html>
