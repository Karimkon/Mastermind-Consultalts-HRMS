@extends('mail.layout')
@section('content')
<h2>A new vacancy in {{ $job->category?->name ?? 'your area' }}</h2>

<p>Hello {{ $seeker->name }},</p>
<p>A position has opened that matches the categories you asked to hear about.</p>

<table class="info-table">
    <tr><td>Position</td><td><strong>{{ $job->title }}</strong></td></tr>
    <tr><td>Category</td><td>{{ $job->category?->name ?? 'General' }}</td></tr>
    <tr><td>Location</td><td>{{ $job->location ?? 'Not specified' }}</td></tr>
    <tr><td>Type</td><td>{{ ucfirst(str_replace('_', ' ', $job->employment_type)) }}</td></tr>
    <tr><td>Vacancies</td><td>{{ $job->vacancies }}</td></tr>
    @if($job->deadline)
    <tr><td>Closing date</td><td>{{ $job->deadline->format('d M Y') }}</td></tr>
    @endif
</table>

<p><a href="{{ route('careers.show', $job) }}">View the position and apply</a></p>

<p style="font-size:12px;color:#64748b">
    You are receiving this because you asked to be told about jobs in this category.
    You can change your categories in the Mastermind Careers app.
</p>
@endsection
