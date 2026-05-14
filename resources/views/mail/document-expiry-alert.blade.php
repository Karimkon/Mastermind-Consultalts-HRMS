@extends('mail.layout')
@section('content')
<h2 style="color:#991b1b;">⚠ Documents Expiring Soon</h2>
<p>Dear HR Team,</p>
<p>The following employee has documents and/or certifications expiring within the next <strong>30 days</strong>. Please take action to renew or update them before they expire.</p>

<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:16px;margin:16px 0;">
    <strong style="font-size:16px;color:#1e293b;">{{ $employee->full_name }}</strong><br>
    <span style="color:#64748b;font-size:14px;">{{ $employee->designation?->title }} &bull; {{ $employee->department?->name }}</span><br>
    <span style="color:#64748b;font-size:14px;">Employee #: {{ $employee->emp_number }}</span>
</div>

@if($documents->isNotEmpty())
<h3 style="color:#92400e;">Expiring Documents ({{ $documents->count() }})</h3>
<table class="info-table">
    <thead>
        <tr style="background:#fef9c3;">
            <th>Document Type</th>
            <th>Title</th>
            <th>Expiry Date</th>
            <th>Days Until Expiry</th>
        </tr>
    </thead>
    <tbody>
        @foreach($documents as $doc)
        @php $daysLeft = now()->diffInDays($doc->expiry_date, false); @endphp
        <tr style="{{ $daysLeft <= 7 ? 'background:#fee2e2;' : ($daysLeft <= 30 ? 'background:#fffbeb;' : '') }}">
            <td>{{ $doc->document_type }}</td>
            <td>{{ $doc->title }}</td>
            <td><strong>{{ \Carbon\Carbon::parse($doc->expiry_date)->format('d M Y') }}</strong></td>
            <td style="font-weight:bold;color:{{ $daysLeft <= 7 ? '#dc2626' : '#d97706' }}">
                {{ (int)$daysLeft }} days {{ $daysLeft <= 0 ? '(EXPIRED)' : '' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if($certifications->isNotEmpty())
<h3 style="color:#1e40af;margin-top:24px;">Expiring Certifications ({{ $certifications->count() }})</h3>
<table class="info-table">
    <thead>
        <tr style="background:#eff6ff;">
            <th>Certification Name</th>
            <th>Issued By</th>
            <th>Expiry Date</th>
            <th>Days Until Expiry</th>
        </tr>
    </thead>
    <tbody>
        @foreach($certifications as $cert)
        @php $daysLeft = now()->diffInDays($cert->expiry_date, false); @endphp
        <tr style="{{ $daysLeft <= 7 ? 'background:#fee2e2;' : ($daysLeft <= 30 ? 'background:#fffbeb;' : '') }}">
            <td>{{ $cert->name }}</td>
            <td>{{ $cert->issued_by }}</td>
            <td><strong>{{ \Carbon\Carbon::parse($cert->expiry_date)->format('d M Y') }}</strong></td>
            <td style="font-weight:bold;color:{{ $daysLeft <= 7 ? '#dc2626' : '#d97706' }}">
                {{ (int)$daysLeft }} days {{ $daysLeft <= 0 ? '(EXPIRED)' : '' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div style="margin-top:24px;padding:16px;background:#f1f5f9;border-radius:8px;">
    <p style="margin:0;"><strong>Action Required:</strong> Please log in to the HR portal to update these documents before they expire.</p>
</div>
<p style="margin-top:16px;">
    <a href="{{ config('app.url') }}/employees/{{ $employee->id }}"
       style="background:#1e40af;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">
        View Employee Profile
    </a>
</p>
@endsection
