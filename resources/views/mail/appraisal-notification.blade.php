@extends('mail.layout')
@section('content')
<h2>{{ $heading }}</h2>

<p>Dear {{ $recipientName ?: 'Colleague' }},</p>

<p>{{ $intro }}</p>

<table class="info-table">
    <tr><td>Appraisal</td><td>{{ $appraisal->title }}</td></tr>
    <tr><td>Staff member</td><td>{{ $appraisal->employee?->full_name ?? '—' }}</td></tr>
    <tr><td>Period</td><td>{{ $appraisal->period ?: $appraisal->year }}</td></tr>
    <tr><td>Stage</td><td>{{ $appraisal->statusLabel() }}</td></tr>
</table>

<p style="margin-top:18px">
    <a href="{{ route('appraisals.show', $appraisal) }}"
       style="display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;
              border-radius:6px;text-decoration:none;font-weight:600">
        Open the scorecard
    </a>
</p>

<p style="font-size:12px;color:#64748b">
    If the button does not work, paste this into your browser:<br>
    {{ route('appraisals.show', $appraisal) }}
</p>
@endsection
