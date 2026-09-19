@extends('mail.layout')
@section('content')
<h2>You have been nominated to cover</h2>

<p>Dear {{ $leave->replacement_name }},</p>

<p>
    <strong>{{ $leave->employee?->full_name }}</strong> has applied for leave and has nominated
    <strong>you</strong> to cover their responsibilities while they are away.
</p>

<table class="info-table">
    <tr><td>Colleague</td><td>{{ $leave->employee?->full_name }}</td></tr>
    <tr><td>Department</td><td>{{ $leave->employee?->department?->name ?? 'N/A' }}</td></tr>
    <tr><td>Leave Type</td><td>{{ $leave->leaveType?->name ?? 'N/A' }}</td></tr>
    <tr><td>From Date</td><td>{{ $leave->from_date->format('M d, Y') }}</td></tr>
    <tr><td>To Date</td><td>{{ $leave->to_date->format('M d, Y') }}</td></tr>
    <tr><td>Duration</td><td>{{ $leave->days_count }} day(s)</td></tr>
    <tr><td>Reason given</td><td>{{ $leave->reason }}</td></tr>
</table>

{{-- The request is still pending at this point. Saying "you are covering"
     before the client has approved would be telling them something untrue. --}}
<p>
    <strong>This request has not been approved yet.</strong> It still needs to go through the
    Account Manager and the client. You will receive a second email confirming the dates
    once it is approved — nothing is expected of you before then.
</p>

<p>
    You are being told now so that you can raise any conflict early: if you are already away
    on those dates, or cannot take this on, please speak to
    {{ $leave->employee?->full_name ?? 'your colleague' }} or HR before it is approved.
</p>

<p>Thank you.</p>
@endsection
