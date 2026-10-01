@extends('mail.layout')
@section('content')
<h2>{{ $event->to_status === 'rejected' ? 'Update on your application' : 'Your application has moved forward' }}</h2>

<p>{{ $event->message }}</p>

<table class="info-table">
    <tr><td>Position</td><td>{{ $candidate->jobPosting?->title ?? 'N/A' }}</td></tr>
    <tr><td>Reference</td><td><strong>{{ $candidate->tracking_code }}</strong></td></tr>
    <tr><td>Stage</td><td><span class="badge badge-blue">{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</span></td></tr>
</table>

@if(!empty($progress))
<p><strong>Where your application stands:</strong></p>
<table class="info-table">
    @foreach($progress as $stage)
    <tr>
        <td>{{ $stage['label'] }}</td>
        <td>
            @if($stage['state'] === 'done')      Completed
            @elseif($stage['state'] === 'current') In progress
            @else                                 Not yet
            @endif
        </td>
    </tr>
    @endforeach
</table>
@endif

<p>
    You can check your application at any time using your reference:<br>
    <a href="{{ route('careers.status.show', $candidate->tracking_code) }}">{{ route('careers.status.show', $candidate->tracking_code) }}</a>
</p>

<p style="font-size:12px;color:#64748b">Please do not reply to this message. If you need to reach us, use the contact details on our careers page.</p>
@endsection
