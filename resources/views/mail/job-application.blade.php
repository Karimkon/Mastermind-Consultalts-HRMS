@extends('mail.layout')
@section('content')
<h2>New Job Application Received</h2>
<p>A new application has been submitted via the public careers page.</p>
<table class="info-table">
    <tr><td>Position</td><td>{{ $candidate->jobPosting?->title ?? 'N/A' }}</td></tr>
    <tr><td>Applicant</td><td>{{ $candidate->first_name }} {{ $candidate->last_name }}</td></tr>
    <tr><td>Email</td><td>{{ $candidate->email }}</td></tr>
    <tr><td>Phone</td><td>{{ $candidate->phone ?? 'Not provided' }}</td></tr>
    <tr><td>Source</td><td><span class="badge badge-blue">Careers Page</span></td></tr>
    <tr><td>Applied</td><td>{{ $candidate->created_at?->format('M d, Y H:i') }}</td></tr>
</table>
@if($candidate->cover_letter)
<p><strong>Cover Letter:</strong></p>
<p style="background:#f8fafc;padding:12px;border-radius:8px;border-left:3px solid #3b82f6;font-size:13px;color:#334155">
    {{ Str::limit($candidate->cover_letter, 400) }}
</p>
@endif
<p>Log in to the HRMS to review the full application and CV.</p>
@endsection
