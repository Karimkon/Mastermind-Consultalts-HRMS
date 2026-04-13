@extends('mail.layout')
@section('content')
<h2>Certification Expiring Soon</h2>
<p>Dear {{ $certification->employee->first_name }},</p>
<p>This is a reminder that one of your certifications is expiring soon. Please take action to renew it before the expiry date.</p>
<table class="info-table">
    <tr><td>Certification</td><td><strong>{{ $certification->name }}</strong></td></tr>
    <tr><td>Issued By</td><td>{{ $certification->issued_by ?? 'N/A' }}</td></tr>
    <tr><td>Issue Date</td><td>{{ $certification->issue_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
    <tr><td>Expiry Date</td><td><strong style="color:#b91c1c">{{ $certification->expiry_date?->format('M d, Y') }}</strong></td></tr>
    <tr><td>Days Remaining</td><td><span class="badge badge-yellow">{{ now()->diffInDays($certification->expiry_date) }} days</span></td></tr>
</table>
<p>Please log in to the HRMS and upload your renewed certification document.</p>
@endsection
