@extends('mail.layout')
@section('content')
<h2>{{ $heading }}</h2>

<p>Dear {{ $employee->first_name ?: 'Colleague' }},</p>

<p>{{ $intro }}</p>

<table class="info-table">
    <tr><td>Employee</td><td>{{ $employee->full_name }}</td></tr>
    <tr><td>Date joined</td><td>{{ $employee->hire_date?->format('d M Y') ?? '—' }}</td></tr>
    @if($outcome === 'extended')
        <tr><td>New probation end date</td>
            <td><strong>{{ $employee->probation_end_date?->format('d M Y') ?? '—' }}</strong></td></tr>
    @else
        <tr><td>Probation end date</td>
            <td>{{ $employee->probation_end_date?->format('d M Y') ?? '—' }}</td></tr>
    @endif
    <tr><td>Decision</td><td><strong>{{ match($outcome) {
        'passed'   => 'Employment confirmed',
        'extended' => 'Probation extended',
        'failed'   => 'Not confirmed',
        default    => ucfirst($outcome),
    } }}</strong></td></tr>
    <tr><td>Decision date</td>
        <td>{{ $employee->probation_confirmed_at?->format('d M Y') ?? now()->format('d M Y') }}</td></tr>
</table>

{{-- Only shown when somebody actually wrote something. An empty "Comments"
     heading on a letter about someone's job reads as an oversight. --}}
@if(filled($notes))
    <p style="margin-top:18px"><strong>Comments from the review</strong></p>
    <p style="white-space:pre-line">{{ $notes }}</p>
@endif

<p style="font-size:12px;color:#64748b;margin-top:22px">
    If anything here looks wrong, please contact the HR department.
</p>
@endsection
