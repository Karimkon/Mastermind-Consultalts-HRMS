<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size:11px; color:#1e293b; }
.page { padding:24px 28px; }
.hdr-table { width:100%; border-collapse:collapse; border-bottom:3px solid #1e40af; padding-bottom:10px; margin-bottom:14px; }
.company-name { font-size:17px; font-weight:700; color:#1e40af; }
.company-sub  { font-size:10px; color:#64748b; margin-top:2px; }
.payslip-title { font-size:14px; font-weight:700; text-align:right; }
.payslip-period{ font-size:10px; color:#64748b; text-align:right; }
.info-tbl { width:100%; background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:12px; border-collapse:collapse; }
.info-tbl td { padding:6px 10px; vertical-align:top; width:33%; }
.lbl { font-size:9px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
.val { font-size:11px; font-weight:600; }
.sec { font-size:10px; font-weight:700; text-transform:uppercase; color:#64748b; margin:12px 0 5px; border-left:3px solid #1e40af; padding-left:8px; }
table.comp { width:100%; border-collapse:collapse; }
table.comp th { background:#f1f5f9; font-size:9px; font-weight:700; text-transform:uppercase; color:#64748b; padding:5px 8px; text-align:left; border-bottom:1px solid #e2e8f0; }
table.comp th.r { text-align:right; }
table.comp td { padding:5px 8px; border-bottom:1px solid #f8fafc; }
table.comp td.r { text-align:right; }
table.comp tr.sub td { background:#f8fafc; font-weight:700; border-top:1px solid #e2e8f0; }
.net-box { margin-top:14px; background:#1e40af; color:#fff; border-radius:6px; padding:12px 16px; }
.net-tbl { width:100%; border-collapse:collapse; }
.net-amt { font-size:22px; font-weight:700; text-align:right; }
.att-tbl { width:100%; margin:8px 0; border-collapse:separate; border-spacing:4px; }
.att-tbl td { text-align:center; background:#f8fafc; border:1px solid #e2e8f0; padding:7px; }
.att-num { font-size:16px; font-weight:700; }
.att-lbl { font-size:9px; color:#64748b; text-transform:uppercase; }
.sum-tbl { width:100%; border-collapse:collapse; margin-top:12px; }
.sum-tbl th { background:#0f172a; color:#fff; padding:5px 8px; font-size:10px; text-align:left; }
.sum-tbl th.r { text-align:right; }
.sum-tbl td { padding:5px 8px; background:#f8fafc; font-weight:700; border:1px solid #e2e8f0; }
.sum-tbl td.r { text-align:right; }
.footer { margin-top:14px; padding-top:8px; border-top:1px solid #e2e8f0; font-size:9px; color:#94a3b8; text-align:center; }
</style>
</head>
<body>
<div class="page">

{{-- Header --}}
<table class="hdr-table"><tr>
<td><div class="company-name">{{ $company['name'] }}</div><div class="company-sub">{{ $company['email'] }} &middot; {{ $company['phone'] }}</div></td>
<td><div class="payslip-title">EMPLOYEE PAYSLIP</div><div class="payslip-period">{{ date('F Y', mktime(0,0,0,$payroll_run->month,1,$payroll_run->year)) }}</div></td>
</tr></table>

{{-- Employee Info --}}
<table class="info-tbl"><tr>
<td><div class="lbl">Employee</div><div class="val">{{ $payslip->employee->full_name }}</div><div style="font-size:10px;color:#64748b">{{ $payslip->employee->emp_number }}</div></td>
<td><div class="lbl">Department / Role</div><div class="val">{{ $payslip->employee->department?->name ?? '—' }}</div><div style="font-size:10px;color:#64748b">{{ $payslip->employee->designation?->title ?? '—' }}</div></td>
<td style="text-align:right"><div class="lbl">Pay Period</div><div class="val">{{ date('F Y', mktime(0,0,0,$payroll_run->month,1,$payroll_run->year)) }}</div>
@if($payroll_run->payment_date)<div style="font-size:10px;color:#64748b">Paid: {{ \Carbon\Carbon::parse($payroll_run->payment_date)->format('d M Y') }}</div>@endif</td>
</tr></table>

{{-- Attendance --}}
<div class="sec">Attendance Summary</div>
<table class="att-tbl"><tr>
<td><div class="att-num" style="color:#166534">{{ $payslip->worked_days }}</div><div class="att-lbl">Present</div></td>
<td><div class="att-num" style="color:#991b1b">{{ $payslip->absent_days }}</div><div class="att-lbl">Absent</div></td>
<td><div class="att-num" style="color:#1e40af">{{ cal_days_in_month(CAL_GREGORIAN, $payroll_run->month, $payroll_run->year) }}</div><div class="att-lbl">Month Days</div></td>
<td><div class="att-num" style="font-size:10px;color:#64748b">{{ $payslip->employee->employment_type ? str_replace('_',' ',ucwords($payslip->employee->employment_type,'_')) : '—' }}</div><div class="att-lbl">Contract</div></td>
</tr></table>

{{-- Earnings --}}
<div class="sec">Earnings</div>
<table class="comp">
<thead><tr><th>Description</th><th class="r">Amount ({{ $company['currency'] }})</th></tr></thead>
<tbody>
<tr><td>Basic Salary</td><td class="r">{{ number_format($payslip->basic_salary, 2) }}</td></tr>
@php $earns = collect($payslip->component_details ?? [])->where('type','allowance'); @endphp
@foreach($earns as $e)
<tr><td>{{ $e['name'] }}</td><td class="r">{{ number_format($e['amount'], 2) }}</td></tr>
@endforeach
<tr class="sub"><td><strong>Total Gross Earnings</strong></td><td class="r"><strong>{{ number_format($payslip->gross_salary, 2) }}</strong></td></tr>
</tbody>
</table>

{{-- Deductions --}}
<div class="sec">Deductions</div>
<table class="comp">
<thead><tr><th>Description</th><th class="r">Amount ({{ $company['currency'] }})</th></tr></thead>
<tbody>
@php $deds = collect($payslip->component_details ?? [])->where('type','deduction'); @endphp
@foreach($deds as $d)
<tr><td>{{ $d['name'] }}</td><td class="r">{{ number_format($d['amount'], 2) }}</td></tr>
@endforeach
<tr class="sub"><td><strong>Total Deductions</strong></td><td class="r"><strong>{{ number_format($payslip->total_deductions, 2) }}</strong></td></tr>
</tbody>
</table>

{{-- Net Pay --}}
<div class="net-box">
<table class="net-tbl"><tr>
<td style="font-size:13px;font-weight:700">NET PAY (Take-Home)</td>
<td class="net-amt">{{ $company['currency'] }} {{ number_format($payslip->net_salary, 2) }}</td>
</tr></table>
</div>

{{-- Summary row --}}
<table class="sum-tbl">
<thead><tr><th>Gross Earnings</th><th class="r">Other Deductions</th><th class="r">PAYE Income Tax</th><th class="r">Net Pay</th></tr></thead>
<tbody><tr>
<td>{{ $company['currency'] }} {{ number_format($payslip->gross_salary, 2) }}</td>
<td class="r">{{ $company['currency'] }} {{ number_format($payslip->total_deductions - $payslip->tax_amount, 2) }}</td>
<td class="r">{{ $company['currency'] }} {{ number_format($payslip->tax_amount, 2) }}</td>
<td class="r" style="color:#166534">{{ $company['currency'] }} {{ number_format($payslip->net_salary, 2) }}</td>
</tr></tbody>
</table>

<div class="footer">Computer-generated payslip — no signature required &middot; {{ now()->format('d M Y H:i') }} &middot; {{ $company['name'] }}</div>
</div>
</body>
</html>
