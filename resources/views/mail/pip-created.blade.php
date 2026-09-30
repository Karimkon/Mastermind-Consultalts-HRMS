@extends('mail.layout')
@section('content')
<h2>{{ count($changes) ? 'Your improvement plan has been updated' : 'A Performance Improvement Plan has been opened' }}</h2>

<p>Dear {{ $pip->employee?->first_name ?: 'Colleague' }},</p>

<p>
    @if(count($changes))
        Your Performance Improvement Plan has been changed. The current details are
        below - please check them and speak to your manager or HR if anything is unclear.
    @else
        A Performance Improvement Plan has been created for you. It sets out what is
        expected and by when, so please read it and speak to your manager or HR if
        anything is unclear.
    @endif
</p>

<table class="info-table">
    <tr><td>Plan</td><td><strong>{{ $pip->title }}</strong></td></tr>
    @if($pip->cycle)
        <tr><td>Cycle</td><td>{{ $pip->cycle->name }}</td></tr>
    @endif
    <tr><td>Starts</td><td>{{ $pip->start_date?->format('d M Y') ?? '—' }}</td></tr>
    <tr><td>Ends</td><td><strong>{{ $pip->end_date?->format('d M Y') ?? '—' }}</strong></td></tr>
</table>


{{-- Named rather than implied: "something changed" makes somebody re-read the
     whole thing to find out what. --}}
@if(count($changes))
    <p style="margin-top:16px"><strong>What changed</strong></p>
    <ul style="margin-top:4px; padding-left:18px">
        @foreach($changes as $field => $change)
            <li style="margin-bottom:4px"><strong>{{ $field }}</strong> — {{ $change }}</li>
        @endforeach
    </ul>
@endif

@if(filled($pip->description))
    <p style="margin-top:16px"><strong>Context</strong></p>
    <p style="white-space:pre-line">{{ $pip->description }}</p>
@endif

@if(is_array($pip->objectives) && count($pip->objectives))
    <p style="margin-top:16px"><strong>What you are being asked to achieve</strong></p>
    <ul style="margin-top:4px; padding-left:18px">
        @foreach($pip->objectives as $objective)
            <li style="margin-bottom:4px">{{ $objective }}</li>
        @endforeach
    </ul>
@endif

<p style="margin-top:18px">
    <a href="{{ route('pips.show', $pip) }}"
       style="display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;
              border-radius:6px;text-decoration:none;font-weight:600">
        Open the plan
    </a>
</p>

<p style="font-size:12px;color:#64748b">
    If the button does not work, paste this into your browser:<br>
    {{ route('pips.show', $pip) }}
</p>
@endsection
