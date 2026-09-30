@extends('mail.layout')
@section('content')
<h2>{{ count($changes) ? 'Your goal has been updated' : 'A new goal has been set for you' }}</h2>

<p>Dear {{ $goal->employee?->first_name ?: 'Colleague' }},</p>

<p>
    @if(count($changes))
        A goal on your performance record has been changed. The current details are
        below - please check them and raise anything unclear with your manager.
    @else
        A goal has been added to your performance record. It is scored at the end of
        the cycle, so please read it and raise anything unclear with your manager.
    @endif
</p>

<table class="info-table">
    <tr><td>Goal</td><td><strong>{{ $goal->title }}</strong></td></tr>
    @if($goal->cycle)
        <tr><td>Cycle</td><td>{{ $goal->cycle->name }}</td></tr>
    @endif
    @if($goal->target_date)
        <tr><td>Target date</td><td><strong>{{ $goal->target_date->format('d M Y') }}</strong></td></tr>
    @endif
    @if($goal->weight > 0)
        <tr><td>Weight</td><td>{{ rtrim(rtrim(number_format($goal->weight, 2), '0'), '.') }}%</td></tr>
    @endif
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

@if(filled($goal->description))
    <p style="margin-top:16px"><strong>What this means</strong></p>
    <p style="white-space:pre-line">{{ $goal->description }}</p>
@endif

<p style="margin-top:18px">
    <a href="{{ route('goals.index') }}"
       style="display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;
              border-radius:6px;text-decoration:none;font-weight:600">
        View your goals
    </a>
</p>

<p style="font-size:12px;color:#64748b">
    If the button does not work, paste this into your browser:<br>
    {{ route('goals.index') }}
</p>
@endsection
