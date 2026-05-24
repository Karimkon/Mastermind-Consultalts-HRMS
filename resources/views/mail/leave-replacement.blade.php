@extends('mail.layout')
@section('content')
<h2>Leave Coverage Notification</h2>
<p>Dear {{ $leave->replacement_name }},</p>
<p>This is to inform you that <strong>{{ $leave->employee?->full_name }}</strong> has been approved for leave and you have been designated as their cover/replacement during this period.</p>
<table class="info-table">
    <tr><td>Employee</td><td>{{ $leave->employee?->full_name }}</td></tr>
    <tr><td>Department</td><td>{{ $leave->employee?->department?->name ?? 'N/A' }}</td></tr>
    <tr><td>Leave Type</td><td>{{ $leave->leaveType?->name ?? 'N/A' }}</td></tr>
    <tr><td>From Date</td><td>{{ $leave->from_date->format('M d, Y') }}</td></tr>
    <tr><td>To Date</td><td>{{ $leave->to_date->format('M d, Y') }}</td></tr>
    <tr><td>Duration</td><td>{{ $leave->days_count }} day(s)</td></tr>
</table>
<p>Please ensure all responsibilities are covered during this period. Contact HR if you have any questions.</p>
<p>Thank you for your support.</p>
@endsection
