@extends('mail.layout')
@section('content')
<h2>Employee Leave Request — Your Approval Needed</h2>
<p>Dear Client,</p>
<p>One of your assigned employees has applied for leave. Your approval is required before the leave can be confirmed.</p>
<table class="info-table">
    <tr><td>Employee</td><td><strong>{{ $leave->employee->full_name }}</strong></td></tr>
    <tr><td>Department</td><td>{{ $leave->employee->department?->name ?? 'N/A' }}</td></tr>
    <tr><td>Leave Type</td><td>{{ $leave->leaveType?->name ?? 'N/A' }}</td></tr>
    <tr><td>From</td><td>{{ $leave->from_date->format('d M Y') }}</td></tr>
    <tr><td>To</td><td>{{ $leave->to_date->format('d M Y') }}</td></tr>
    <tr><td>Days Requested</td><td><strong>{{ $leave->days_count }} day(s)</strong></td></tr>
    <tr><td>Reason</td><td>{{ $leave->reason }}</td></tr>
</table>
@if($leave->replacement_name)
<h3 style="margin-top:20px;color:#1e40af;">Cover / Replacement Person Arranged</h3>
<p>The employee has arranged the following person to cover during their absence:</p>
<table class="info-table">
    <tr><td>Name</td><td><strong>{{ $leave->replacement_name }}</strong></td></tr>
    <tr><td>Email</td><td>{{ $leave->replacement_email }}</td></tr>
    <tr><td>Phone</td><td>{{ $leave->replacement_phone }}</td></tr>
</table>
@endif
<p style="margin-top:20px;">Please log in to the <a href="{{ config('app.url') }}/client/leaves">Client Portal</a> to approve or reject this leave request.</p>
@endsection
