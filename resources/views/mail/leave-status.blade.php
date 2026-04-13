@extends('mail.layout')
@section('content')
<h2>Leave Request {{ ucfirst($leave->status) }}</h2>
<p>Dear {{ $leave->employee->first_name }},</p>
@if($leave->status === 'approved')
<p>Great news! Your leave request has been <span class="badge badge-green">Approved</span>.</p>
@elseif($leave->status === 'rejected')
<p>Unfortunately, your leave request has been <span class="badge badge-red">Rejected</span>.</p>
@endif
<table class="info-table">
    <tr><td>Leave Type</td><td>{{ $leave->leaveType?->name ?? 'N/A' }}</td></tr>
    <tr><td>From</td><td>{{ $leave->from_date->format('M d, Y') }}</td></tr>
    <tr><td>To</td><td>{{ $leave->to_date->format('M d, Y') }}</td></tr>
    <tr><td>Days</td><td>{{ $leave->days_count }} day(s)</td></tr>
    <tr><td>Status</td><td><span class="badge badge-{{ $leave->status === 'approved' ? 'green' : 'red' }}">{{ ucfirst($leave->status) }}</span></td></tr>
    @if($leave->rejection_reason)
    <tr><td>Reason</td><td>{{ $leave->rejection_reason }}</td></tr>
    @endif
</table>
<p>Please log in to the HRMS system for more details.</p>
@endsection
