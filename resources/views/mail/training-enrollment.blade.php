@extends('mail.layout')
@section('content')
<h2>Training Enrollment Confirmation</h2>
<p>Dear {{ $enrollment->employee->first_name }},</p>
<p>You have been enrolled in the following training course:</p>
<table class="info-table">
    <tr><td>Course</td><td><strong>{{ $enrollment->course->title }}</strong></td></tr>
    <tr><td>Category</td><td>{{ $enrollment->course->category ?? 'General' }}</td></tr>
    <tr><td>Duration</td><td>{{ $enrollment->course->duration_hours }} hours</td></tr>
    @if($enrollment->course->trainer_name)
    <tr><td>Trainer</td><td>{{ $enrollment->course->trainer_name }}</td></tr>
    @endif
    @if($enrollment->course->external_url)
    <tr><td>Access Link</td><td><a href="{{ $enrollment->course->external_url }}">Click here</a></td></tr>
    @endif
    <tr><td>Status</td><td><span class="badge badge-blue">Enrolled</span></td></tr>
</table>
<p>Log in to the HRMS to track your progress and access course materials.</p>
@endsection
