@extends('mail.layout')
@section('content')
<h2>Meeting Invitation</h2>
<p>Dear {{ $participant->first_name }},</p>
<p>You have been invited to the following meeting:</p>
<table class="info-table">
    <tr><td>Title</td><td><strong>{{ $meeting->title }}</strong></td></tr>
    <tr><td>Date</td><td>{{ \Carbon\Carbon::parse($meeting->start_at)->format('l, F j, Y') }}</td></tr>
    <tr><td>Time</td><td>{{ \Carbon\Carbon::parse($meeting->start_at)->format('H:i') }} – {{ \Carbon\Carbon::parse($meeting->end_at)->format('H:i') }}</td></tr>
    <tr><td>Location</td><td>{{ $meeting->location ?? 'TBD' }}</td></tr>
    @if($meeting->meeting_url)
    <tr><td>Join Link</td><td><a href="{{ $meeting->meeting_url }}">{{ $meeting->meeting_url }}</a></td></tr>
    @endif
    <tr><td>Organizer</td><td>{{ $meeting->organizer?->full_name ?? 'N/A' }}</td></tr>
    @if($meeting->agenda)
    <tr><td>Agenda</td><td>{{ $meeting->agenda }}</td></tr>
    @endif
</table>
<p>Please log in to the HRMS to RSVP to this meeting.</p>
@endsection
