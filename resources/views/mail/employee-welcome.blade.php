@extends('mail.layout')
@section('content')
<h2>Welcome to Mastermind Consultants</h2>

<p>Dear {{ $employee->first_name ?: 'Colleague' }},</p>

<p>
    Welcome to the team. Your record has been created on the Mastermind
    Consultants HR system, where you will find your profile, attendance, leave,
    payslips and appraisals in one place.
</p>

<table class="info-table">
    <tr><td>Name</td><td>{{ $employee->full_name }}</td></tr>
    <tr><td>Staff number</td><td><strong>{{ $employee->emp_number }}</strong></td></tr>
    @if($position)
        <tr><td>Position</td><td><strong>{{ $position }}</strong></td></tr>
    @endif
    @if($department)
        <tr><td>Department</td><td>{{ $department }}</td></tr>
    @endif
    @if($supervisor)
        <tr><td>Reports to</td><td>{{ $supervisor }}</td></tr>
    @endif
</table>

@if($loginEmail)
<p style="margin-top:18px"><strong>Signing in</strong></p>
<p>
    Your username is <strong>{{ $loginEmail }}</strong>.
    Your password is being shared with you separately - please change it the
    first time you sign in, under Profile.
</p>

<p style="margin-top:14px">
    <a href="{{ url('/login') }}"
       style="display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;
              border-radius:6px;text-decoration:none;font-weight:600">
        Open the HR system
    </a>
</p>

<p style="font-size:12px;color:#64748b">
    If the button does not work, paste this into your browser:<br>
    {{ url('/login') }}
</p>

<p style="font-size:12px;color:#64748b">
    If you ever forget your password, use "Forgot password" on the sign-in page
    and a reset link will be emailed to this address.
</p>
@endif

<p style="font-size:12px;color:#64748b;margin-top:22px">
    If anything above is wrong, please contact the HR department.
</p>
@endsection
