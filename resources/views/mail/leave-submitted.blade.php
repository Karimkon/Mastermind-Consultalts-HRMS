@extends('mail.layout')
@section('content')
<h2>New Leave Request — Action Required</h2>
<p>A new leave request has been submitted by one of your employees and requires your review and approval.</p>
<table class="info-table">
    <tr><td>Employee</td><td><strong>{{ $leave->employee->full_name }}</strong></td></tr>
    <tr><td>Department</td><td>{{ $leave->employee->department?->name ?? 'N/A' }}</td></tr>
    <tr><td>Leave Type</td><td>{{ $leave->leaveType?->name ?? 'N/A' }}</td></tr>
    <tr><td>From</td><td>{{ $leave->from_date->format('d M Y') }}</td></tr>
    <tr><td>To</td><td>{{ $leave->to_date->format('d M Y') }}</td></tr>
    <tr><td>Days Requested</td><td>{{ $leave->days_count }} day(s)</td></tr>
    <tr><td>Reason</td><td>{{ $leave->reason }}</td></tr>
    <tr><td>Status</td><td><span class="badge badge-yellow">Pending Approval</span></td></tr>
</table>
@if($leave->replacement_name)
<h3 style="margin-top:20px;">Replacement / Cover Person</h3>
<table class="info-table">
    <tr><td>Name</td><td><strong>{{ $leave->replacement_name }}</strong></td></tr>
    <tr><td>Email</td><td>{{ $leave->replacement_email }}</td></tr>
    <tr><td>Phone</td><td>{{ $leave->replacement_phone }}</td></tr>
</table>
@endif
<p>Please log in to the HRMS to approve or reject this request.</p>
@endsection
