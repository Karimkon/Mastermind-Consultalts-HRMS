@extends('mail.layout')
@section('content')
<h2 style="color:{{ $urgency === 'urgent' ? '#dc2626' : '#d97706' }}">
    {{ $urgency === 'urgent' ? 'URGENT' : 'Notice' }}: Employee Contract Expiring in {{ $days }} Days
</h2>

<p>This is an automated alert to inform you that the following employee's contract is due to expire soon.</p>

<table class="info-table">
    <tr><td>Employee</td><td><strong>{{ $employee->full_name }}</strong></td></tr>
    <tr><td>Employee No.</td><td>{{ $employee->emp_number }}</td></tr>
    @if($employee->payroll_number)
    <tr><td>Payroll No.</td><td>{{ $employee->payroll_number }}</td></tr>
    @endif
    <tr><td>Department</td><td>{{ $employee->department?->name ?? '—' }}</td></tr>
    <tr><td>Designation</td><td>{{ $employee->designation?->title ?? '—' }}</td></tr>
    @if($client)
    <tr><td>Client / Site</td><td>{{ $client->company_name }}</td></tr>
    @endif
    <tr><td>Contract End Date</td><td><strong style="color:#dc2626;">{{ $employee->end_date?->format('d M Y') }}</strong></td></tr>
    <tr><td>Days Remaining</td><td><strong>{{ $days }} day(s)</strong></td></tr>
    <tr><td>Employment Type</td><td>{{ ucfirst(str_replace('_', ' ', $employee->employment_type ?? '—')) }}</td></tr>
</table>

<p style="margin-top:16px;">
    @if($urgency === 'urgent')
    <strong style="color:#dc2626;">Immediate action required.</strong> Please initiate renewal, extension, or termination proceedings as soon as possible.
    @else
    Please review this contract and take the necessary action — renewal, extension, or planned termination — before the expiry date.
    @endif
</p>

<p>Log in to the HRMS to manage this employee's contract.</p>
@endsection
