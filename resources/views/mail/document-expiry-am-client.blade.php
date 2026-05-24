@extends('mail.layout')
@section('content')
@php
    $isUrgent = $days <= 7;
    $color    = $isUrgent ? '#991b1b' : '#92400e';
    $bgColor  = $isUrgent ? '#fef2f2' : '#fffbeb';
    $bdColor  = $isUrgent ? '#fecaca' : '#fde68a';
    $greeting = $recipientType === 'am' ? 'Dear Account Manager,' : 'Dear ' . $client->contact_person . ',';
    $roleNote = $recipientType === 'am'
        ? 'As the Account Manager for <strong>' . $client->company_name . '</strong>, please ensure the employee\'s documents are renewed promptly.'
        : 'This notification is to inform you that an employee placed at <strong>' . $client->company_name . '</strong> has documents requiring immediate attention.';
@endphp

<h2 style="color:{{ $color }};">
    {{ $isUrgent ? '🚨 URGENT: Documents Expiring in ' . $days . ' Days' : '⚠ Alert: Documents Expiring in ' . $days . ' Days' }}
</h2>

<p>{{ $greeting }}</p>
<p>{!! $roleNote !!}</p>

{{-- Employee card --}}
<div style="background:{{ $bgColor }};border:1px solid {{ $bdColor }};border-radius:8px;padding:16px;margin:16px 0;">
    <strong style="font-size:16px;color:#1e293b;">{{ $employee->full_name }}</strong><br>
    <span style="color:#64748b;font-size:14px;">
        {{ $employee->designation?->name ?? $employee->designation?->title ?? '' }}
        @if($employee->department) &bull; {{ $employee->department->name }} @endif
    </span><br>
    <span style="color:#64748b;font-size:14px;">Employee #: {{ $employee->emp_number }}</span><br>
    <span style="color:#64748b;font-size:14px;">Company: <strong>{{ $client->company_name }}</strong></span>
</div>

@if($documents->isNotEmpty())
<h3 style="color:#92400e;margin-top:20px;">Expiring Documents ({{ $documents->count() }})</h3>
<table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
        <tr style="background:#fef9c3;">
            <th style="padding:8px;text-align:left;border:1px solid #e2e8f0;">Document Type</th>
            <th style="padding:8px;text-align:left;border:1px solid #e2e8f0;">Title</th>
            <th style="padding:8px;text-align:left;border:1px solid #e2e8f0;">Expiry Date</th>
            <th style="padding:8px;text-align:center;border:1px solid #e2e8f0;">Days Left</th>
        </tr>
    </thead>
    <tbody>
        @foreach($documents as $doc)
        @php $daysLeft = (int) now()->diffInDays($doc->expiry_date, false); @endphp
        <tr style="background:{{ $daysLeft <= 7 ? '#fee2e2' : '#fffbeb' }};">
            <td style="padding:8px;border:1px solid #e2e8f0;">{{ ucwords(str_replace('_',' ',$doc->document_type)) }}</td>
            <td style="padding:8px;border:1px solid #e2e8f0;">{{ $doc->title }}</td>
            <td style="padding:8px;border:1px solid #e2e8f0;"><strong>{{ \Carbon\Carbon::parse($doc->expiry_date)->format('d M Y') }}</strong></td>
            <td style="padding:8px;text-align:center;border:1px solid #e2e8f0;font-weight:bold;color:{{ $daysLeft <= 7 ? '#dc2626' : '#d97706' }}">
                {{ $daysLeft }} day{{ $daysLeft == 1 ? '' : 's' }}{{ $daysLeft <= 0 ? ' (EXPIRED)' : '' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if($certifications->isNotEmpty())
<h3 style="color:#1e40af;margin-top:24px;">Expiring Certifications ({{ $certifications->count() }})</h3>
<table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
        <tr style="background:#eff6ff;">
            <th style="padding:8px;text-align:left;border:1px solid #e2e8f0;">Certification</th>
            <th style="padding:8px;text-align:left;border:1px solid #e2e8f0;">Issued By</th>
            <th style="padding:8px;text-align:left;border:1px solid #e2e8f0;">Expiry Date</th>
            <th style="padding:8px;text-align:center;border:1px solid #e2e8f0;">Days Left</th>
        </tr>
    </thead>
    <tbody>
        @foreach($certifications as $cert)
        @php $daysLeft = (int) now()->diffInDays($cert->expiry_date, false); @endphp
        <tr style="background:{{ $daysLeft <= 7 ? '#fee2e2' : '#fffbeb' }};">
            <td style="padding:8px;border:1px solid #e2e8f0;">{{ $cert->name }}</td>
            <td style="padding:8px;border:1px solid #e2e8f0;">{{ $cert->issued_by ?? '—' }}</td>
            <td style="padding:8px;border:1px solid #e2e8f0;"><strong>{{ \Carbon\Carbon::parse($cert->expiry_date)->format('d M Y') }}</strong></td>
            <td style="padding:8px;text-align:center;border:1px solid #e2e8f0;font-weight:bold;color:{{ $daysLeft <= 7 ? '#dc2626' : '#d97706' }}">
                {{ $daysLeft }} day{{ $daysLeft == 1 ? '' : 's' }}{{ $daysLeft <= 0 ? ' (EXPIRED)' : '' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Action box --}}
<div style="margin-top:24px;padding:16px;background:{{ $isUrgent ? '#fef2f2' : '#f1f5f9' }};border-left:4px solid {{ $isUrgent ? '#dc2626' : '#f59e0b' }};border-radius:6px;">
    @if($isUrgent)
    <p style="margin:0;font-weight:bold;color:#991b1b;">⚡ Urgent Action Required</p>
    <p style="margin:8px 0 0;">These documents expire in <strong>{{ $days }} days</strong>. Please arrange renewal immediately to avoid compliance issues.</p>
    @else
    <p style="margin:0;font-weight:bold;color:#92400e;">📋 Action Required within 30 Days</p>
    <p style="margin:8px 0 0;">Please arrange renewal of these documents before they expire.</p>
    @endif
</div>

@if($recipientType === 'am')
<p style="margin-top:20px;">
    <a href="{{ config('app.url') }}/account-manager/employees/{{ $employee->id }}"
       style="background:#1e3a8a;color:white;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
        View Employee Profile
    </a>
</p>
@endif

<p style="color:#94a3b8;font-size:12px;margin-top:24px;">
    This is an automated alert from the Mastermind HRMS. Please do not reply to this email.
</p>
@endsection
