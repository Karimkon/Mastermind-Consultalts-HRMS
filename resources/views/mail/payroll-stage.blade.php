@extends('mail.layout')
@section('content')
<h2>{{ $heading }}</h2>

<p>{{ $action }}</p>

<table class="info-table">
    <tr><td>Payroll run</td><td><strong>{{ $run->title }}</strong></td></tr>
    @if($run->client)
        <tr><td>Client</td><td>{{ $run->client->company_name }}</td></tr>
    @endif
    <tr><td>Period</td><td>{{ \Carbon\Carbon::create($run->year, $run->month, 1)->format('F Y') }}</td></tr>
    <tr><td>Employees</td><td>{{ $run->payslips()->count() }}</td></tr>
    <tr><td>Total net</td><td><strong>UGX {{ number_format((float) $run->payslips()->sum('net_salary'), 0) }}</strong></td></tr>
</table>

{{-- A send-back without its reason just bounces the run around the chain. --}}
@if($comment)
<p style="background:#fef9c3;border-left:4px solid #eab308;padding:12px 16px;border-radius:6px;color:#713f12">
    <strong>Reason given{{ $fromName ? ' by ' . $fromName : '' }}:</strong><br>{{ $comment }}
</p>
@endif

<a href="{{ route('payroll.show', $run) }}" class="btn">Open this payroll run</a>
@endsection
