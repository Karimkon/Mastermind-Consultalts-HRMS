@extends('mail.layout')
@section('content')
<h2>Your Leave Request Has Been Approved ✓</h2>
<p>Dear {{ $leave->employee->first_name }},</p>
<p>Great news! Your leave request has been reviewed and <strong style="color:#16a34a;">approved by the client</strong>.</p>
<table class="info-table">
    <tr><td>Leave Type</td><td>{{ $leave->leaveType?->name }}</td></tr>
    <tr><td>From</td><td>{{ $leave->from_date->format('d M Y') }}</td></tr>
    <tr><td>To</td><td>{{ $leave->to_date->format('d M Y') }}</td></tr>
    <tr><td>Total Days</td><td><strong>{{ $leave->days_count }} day(s)</strong></td></tr>
    <tr><td>Status</td><td><span style="color:#16a34a;font-weight:bold;">Approved</span></td></tr>
</table>
<p style="margin-top:16px;">Please ensure your replacement / cover person is fully briefed before your leave starts.</p>
@if($leave->replacement_name)
<p><strong>Your arranged cover person:</strong> {{ $leave->replacement_name }} ({{ $leave->replacement_phone }})</p>
@endif
<p>Have a good leave!</p>
@endsection
