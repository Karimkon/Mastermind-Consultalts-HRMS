@extends('mail.layout')
@section('content')
<h2>Your Payslip is Ready</h2>
<p>Dear {{ $payslip->employee->first_name }},</p>
<p>Your payslip for <strong>{{ $payslip->payrollRun?->title ?? 'this pay period' }}</strong> has been processed and is now available in the HRMS.</p>
<table class="info-table">
    <tr><td>Pay Period</td><td>{{ $payslip->payrollRun?->title ?? 'N/A' }}</td></tr>
    <tr><td>Basic Salary</td><td>UGX {{ number_format($payslip->basic_salary, 0) }}</td></tr>
    <tr><td>Gross Salary</td><td>UGX {{ number_format($payslip->gross_salary, 0) }}</td></tr>
    <tr><td>Total Deductions</td><td>UGX {{ number_format($payslip->total_deductions + $payslip->tax_amount, 0) }}</td></tr>
    <tr><td>Net Salary</td><td><strong>UGX {{ number_format($payslip->net_salary, 0) }}</strong></td></tr>
</table>
<p>Log in to the HRMS portal to view and download your full payslip PDF.</p>
@endsection
