@extends('mail.layout')
@section('content')
<h2>{{ $heading }}</h2>

<p>{{ $intro }}</p>

<table class="info-table">
    <tr><td>Training</td><td><strong>{{ $session->title }}</strong></td></tr>
    @if($session->category)
        <tr><td>Area</td><td>{{ $session->category }}</td></tr>
    @endif
    <tr><td>Delivery</td><td>{{ ucwords(str_replace('_', ' ', $session->delivery)) }}</td></tr>
    @if($session->starts_on)
        <tr><td>When</td><td><strong>{{ $session->starts_on->format('d M Y') }}</strong>@if($session->ends_on && !$session->ends_on->isSameDay($session->starts_on)) – {{ $session->ends_on->format('d M Y') }}@endif</td></tr>
    @endif
    @if($session->duration_days || $session->duration_hours)
        <tr><td>Duration</td><td>
            {{ $session->duration_days ? rtrim(rtrim(number_format($session->duration_days,1),'0'),'.') . ' day(s) ' : '' }}
            {{ $session->duration_hours ? rtrim(rtrim(number_format($session->duration_hours,1),'0'),'.') . ' hour(s)' : '' }}
        </td></tr>
    @endif
    @if($session->venue)
        <tr><td>Where</td><td>{{ $session->venue }}</td></tr>
    @endif
    @if($session->trainer || $session->provider)
        <tr><td>Trainer</td><td>{{ $session->trainer ?: $session->provider }}</td></tr>
    @endif
    <tr><td>Status</td><td>{{ $session->statusLabel() }}</td></tr>
</table>

@if(filled($session->justification))
    <p style="margin-top:16px"><strong>Why it is needed</strong></p>
    <p style="white-space:pre-line">{{ $session->justification }}</p>
@endif

<p style="margin-top:18px">
    <a href="{{ route('training.plan.show', $session) }}"
       style="display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;
              border-radius:6px;text-decoration:none;font-weight:600">
        Open the training plan
    </a>
</p>

<p style="font-size:12px;color:#64748b">
    If the button does not work, paste this into your browser:<br>
    {{ route('training.plan.show', $session) }}
</p>
@endsection
