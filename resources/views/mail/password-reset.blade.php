@extends('mail.layout')
@section('content')
<h2>Reset Your Password</h2>
<p>You are receiving this email because we received a password reset request for your account.</p>
<table class="info-table">
    <tr><td>Requested for</td><td>{{ $notifiable->email }}</td></tr>
    <tr><td>Link expires</td><td>{{ $expiry }} minutes from now</td></tr>
</table>
<div style="text-align:center">
    <a href="{{ $url }}" class="btn">Reset Password</a>
</div>
<p style="margin-top:24px;font-size:13px;color:#94a3b8">
    If you did not request a password reset, no further action is required.<br>
    This link will expire in {{ $expiry }} minutes.
</p>
<p style="font-size:13px;color:#94a3b8">
    If the button above doesn't work, copy and paste this link into your browser:<br>
    <span style="color:#3b82f6;word-break:break-all">{{ $url }}</span>
</p>
@endsection
