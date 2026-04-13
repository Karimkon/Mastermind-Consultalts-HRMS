@extends('mail.layout')
@section('content')
<h2>New Leave Request Submitted</h2>
<p>A new leave request has been submitted and requires your review.</p>
<table class="info-table">
    <tr><td>Employee</td><td>{{ $leave->employee->full_name }}</td></tr>
    <tr><td>Department</td><td>{{ $leave->employee->department?->name ?? 'N/A' }}</td></tr>
    <tr><td>Leave Type</td><td>{{ $leave->leaveType?->name ?? 'N/A' }}</td></tr>
    <tr><td>From</td><td>{{ $leave->from_date->format('M d, Y') }}</td></tr>
    <tr><td>To</td><td>{{ $leave->to_date->format('M d, Y') }}</td></tr>
    <tr><td>Days</td><td>{{ $leave->days_count }} day(s)</td></tr>
    <tr><td>Reason</td><td>{{ $leave->reason }}</td></tr>
    <tr><td>Status</td><td><span class="badge badge-yellow">Pending Approval</span></td></tr>
</table>
<p>Please log in to the HRMS to approve or reject this request.</p>
@endsection
