<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size:10px; color:#111; background:#fff; }
.page { padding:22px 26px; }

table { border-collapse:collapse; }

/* ── Header ── */
.hdr { width:100%; }
.hdr td { vertical-align:middle; padding:0; }
.co-name { font-size:14px; font-weight:700; color:#ea580c; letter-spacing:.02em; }
.co-addr { font-size:8px; color:#555; line-height:1.65; margin-top:3px; }
.slip-badge { background:#ea580c; color:#fff; font-size:12px; font-weight:700;
              padding:9px 16px; text-align:center; letter-spacing:.08em; }

/* ── Orange divider ── */
.rule { border:none; border-top:2.5px solid #ea580c; margin:9px 0; }

/* ── Employee info row ── */
.info { width:100%; margin-bottom:11px; }
.info th { background:#ea580c; color:#fff; font-size:7.5px; font-weight:700;
           text-transform:uppercase; letter-spacing:.04em; padding:5px 5px;
           text-align:center; border:1px solid #c2410c; }
.info td { padding:5px 5px; text-align:center; font-size:9.5px; font-weight:600;
           border:1px solid #d1d5db; background:#fff; }

/* ── Main earnings/deductions/balances table ── */
.mt { width:100%; margin-bottom:11px; }
.mt th { background:#ea580c; color:#fff; font-size:8px; font-weight:700;
         text-transform:uppercase; letter-spacing:.03em; padding:5px 7px;
         border:1px solid #c2410c; text-align:left; }
.mt th.r { text-align:right; }
.mt td { padding:4px 7px; border:1px solid #e5e7eb; font-size:9.5px; vertical-align:middle; }
.mt td.r { text-align:right; }
.mt td.bal { font-weight:700; font-size:8.5px; color:#374151; background:#f9fafb; }
.mt td.bval { font-weight:700; text-align:right; background:#f9fafb; }
.mt tr.totrow td { background:#f3f4f6; font-weight:700; border-top:1.5px solid #ea580c; }

/* ── Total Remitted ── */
.remit { width:100%; margin-bottom:11px; }
.remit td { padding:8px 10px; }
.remit td.lbl { background:#111827; color:#fff; font-weight:700; font-size:11px;
                letter-spacing:.04em; width:70%; }
.remit td.amt { background:#ea580c; color:#fff; font-weight:700; font-size:14px;
                text-align:right; }

/* ── Bank / Personal Details ── */
.bank { width:100%; margin-bottom:0; }
.bank th { background:#ea580c; color:#fff; font-size:8px; font-weight:700;
           text-transform:uppercase; letter-spacing:.03em; padding:5px 7px;
           border:1px solid #c2410c; text-align:center; }
.bank td { padding:6px 7px; border:1px solid #e5e7eb; font-size:10px;
           font-weight:600; text-align:center; }

/* ── Footer ── */
.footer { margin-top:14px; padding-top:6px; border-top:1px solid #e5e7eb;
          font-size:7.5px; color:#9ca3af; text-align:center; }
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

  // Component lists
  $allComps = collect($payslip->component_details ?? []);
  $earns    = $allComps->where('type', 'allowance')->values();
  $deds     = $allComps->where('type', 'deduction')->values();

  // Build rows arrays
  $earnRows = [['Basic Pay', $payslip->basic_salary]];
  foreach ($earns as $e) $earnRows[] = [$e['name'], $e['amount']];

  $dedRows = [];
  foreach ($deds as $d) $dedRows[] = [$d['name'], $d['amount']];

  $balRows = [
      ['TAXABLE INCOME',   $payslip->gross_salary],
      ['TOTAL DEDUCTIONS', $payslip->total_deductions],
      ['NET PAY',          $payslip->net_salary],
  ];

  $rowCount = max(count($earnRows), count($dedRows), count($balRows));
@endphp

{{-- ── Header: Logo | Company + Title | Employee Photo ── --}}
<table class="hdr">
<tr>
  {{-- Logo --}}
  <td style="width:140px; vertical-align:middle;">
    @if(!empty($logo))
      <img src="{{ $logo }}" style="width:130px; height:auto;" alt="Mastermind Logo">
    @else
      <div style="font-size:22px; font-weight:900; color:#ea580c;">MM</div>
    @endif
  </td>
  {{-- Company info + centered title --}}
  <td style="vertical-align:middle; text-align:center; padding:0 8px;">
    <div class="co-name">MASTERMIND CONSULT LTD</div>
    <div class="co-addr">
      Plot 28A Katula Road, Kisasi, P.O. Box 74915, Kampala-Uganda<br>
      Tel: +256 393 215 289 &nbsp;&nbsp; www.mastermindconsults.co.ug
    </div>
    <div style="margin-top:7px;">
      <div class="slip-badge" style="display:inline-block; padding:7px 22px;">PAYMENT ADVISE SLIP</div>
    </div>
  </td>
  {{-- Employee Photo --}}
  <td style="width:80px; vertical-align:middle; text-align:center;">
    @if(!empty($avatar))
      <img src="{{ $avatar }}" style="width:70px; height:70px; object-fit:cover; border-radius:4px; border:2px solid #ea580c;" alt="Photo">
    @else
      <div style="width:70px; height:70px; background:#f3f4f6; border:2px solid #ea580c; display:flex; align-items:center; justify-content:center; font-size:22px; color:#9ca3af; border-radius:4px;">
        <span style="font-size:28px; color:#d1d5db;">&#9786;</span>
      </div>
    @endif
    <div style="font-size:7px; color:#6b7280; margin-top:3px;">{{ $payslip->employee->emp_number }}</div>
  </td>
</tr>
</table>

<hr class="rule">

{{-- ── Employee Info Row ── --}}
<table class="info">
<thead>
<tr>
  <th style="width:22%">NAME</th>
  <th style="width:13%">ROLE</th>
  <th style="width:13%">DEPLOYMENT</th>
  <th style="width:11%">PERIOD</th>
  <th style="width:11%">PAY DATE</th>
  <th style="width:10%">PAY FREQ</th>
  <th style="width:10%">EMP NO</th>
  <th style="width:10%">DAYS WORKED</th>
</tr>
</thead>
<tbody>
<tr>
  <td>{{ $emp->full_name }}</td>
  <td>{{ $emp->designation?->title ?? '—' }}</td>
  <td>{{ $deployment }}</td>
  <td>{{ $period }}</td>
  <td>{{ $payDate }}</td>
  <td>Monthly</td>
  <td>{{ $emp->emp_number }}</td>
  <td>{{ $payslip->worked_days }}</td>
</tr>
</tbody>
</table>

{{-- ── Main Earnings / Deductions / Balances Table ── --}}
<table class="mt">
<thead>
<tr>
  <th style="width:22%">PAYMENTS</th>
  <th class="r" style="width:12%">AMOUNT (UGX)</th>
  <th style="width:22%">DEDUCTIONS</th>
  <th class="r" style="width:12%">AMOUNT (UGX)</th>
  <th style="width:18%">BALANCES</th>
  <th class="r" style="width:14%">NET SALARY (UGX)</th>
</tr>
</thead>
<tbody>
@for($i = 0; $i < $rowCount; $i++)
<tr>
  @if(isset($earnRows[$i]))
    <td>{{ $earnRows[$i][0] }}</td>
    <td class="r">{{ number_format($earnRows[$i][1]) }}</td>
  @else
    <td></td><td></td>
  @endif
  @if(isset($dedRows[$i]))
    <td>{{ $dedRows[$i][0] }}</td>
    <td class="r">{{ number_format($dedRows[$i][1]) }}</td>
  @else
    <td></td><td></td>
  @endif
  @if(isset($balRows[$i]))
    <td class="bal">{{ $balRows[$i][0] }}</td>
    <td class="bval">{{ number_format($balRows[$i][1]) }}</td>
  @else
    <td></td><td></td>
  @endif
</tr>
@endfor
<tr class="totrow">
  <td>TOTAL PAYMENTS</td>
  <td class="r">{{ number_format($payslip->gross_salary) }}</td>
  <td>TOTAL DEDUCTIONS</td>
  <td class="r">{{ number_format($payslip->total_deductions) }}</td>
  <td></td>
  <td></td>
</tr>
</tbody>
</table>

{{-- ── Total Remitted ── --}}
<table class="remit">
<tr>
  <td class="lbl">TOTAL REMITTED</td>
  <td class="amt">UGX &nbsp; {{ number_format($payslip->net_salary) }}</td>
</tr>
</table>

{{-- ── Personal / Bank Details ── --}}
<table class="bank">
<thead>
<tr>
  <th style="width:33%">BANK NAME</th>
  <th style="width:34%">ACCOUNT NAME</th>
  <th style="width:33%">BANK ACCOUNT</th>
</tr>
</thead>
<tbody>
<tr>
  <td>{{ $emp->bank_name ?: '—' }}</td>
  <td>{{ $emp->full_name }}</td>
  <td>{{ $emp->bank_account ?: '—' }}</td>
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
