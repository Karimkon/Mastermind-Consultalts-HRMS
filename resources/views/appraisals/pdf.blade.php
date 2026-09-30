@extends('layouts.pdf')
@section('title', 'Balanced Score Card — ' . ($appraisal->employee?->full_name ?? ''))

@section('content')
<div class="header">
    <h1>Individual Balanced Score Card</h1>
    <p>{{ $appraisal->title }} · {{ $appraisal->period ?: $appraisal->year }}</p>
</div>

<div class="content">

    {{-- Who and when. Mirrors the head of the on-screen card so a printed copy
         can be read against the system without translation. --}}
    <table style="margin-bottom:14px">
        <tr>
            <td style="width:50%; border:none; vertical-align:top; padding-left:0">
                <strong>Employee:</strong> {{ $appraisal->employee?->full_name ?? '—' }}<br>
                <strong>Staff No:</strong> {{ $appraisal->employee?->emp_number ?? '—' }}<br>
                <strong>Job Title:</strong> {{ $appraisal->employee?->designation?->title ?? '—' }}<br>
                <strong>Department:</strong> {{ $appraisal->employee?->department?->name ?? '—' }}
            </td>
            <td style="width:50%; border:none; vertical-align:top">
                <strong>Appraised by:</strong> {{ $appraisal->appraiser?->name ?? '—' }}<br>
                <strong>Immediate Manager:</strong> {{ $appraisal->initiator?->name ?? '—' }}<br>
                <strong>Review Period:</strong>
                {{ $appraisal->review_from?->format('d M Y') ?? '—' }} –
                {{ $appraisal->review_to?->format('d M Y') ?? '—' }}<br>
                <strong>Status:</strong> {{ $appraisal->statusLabel() }}
            </td>
        </tr>
    </table>

    @foreach($grouped as $key => $group)
        <p style="background:#e2e8f0; padding:6px 10px; font-weight:bold; margin-top:12px">
            {{ $loop->iteration }}. {{ strtoupper($group['label']) }} —
            {{ rtrim(rtrim(number_format($group['weight'], 2), '0'), '.') }}%
        </p>

        <table>
            <thead>
                <tr>
                    <th style="width:22%">Key Result Area</th>
                    <th style="width:24%">Performance Measures</th>
                    <th style="width:9%" class="text-right">Target</th>
                    <th style="width:9%" class="text-right">Actual</th>
                    <th style="width:9%" class="text-right">% Achieved</th>
                    <th style="width:8%" class="text-right">Rating</th>
                    <th style="width:9%" class="text-right">Weight</th>
                    <th style="width:10%" class="text-right">Index</th>
                </tr>
            </thead>
            <tbody>
                @forelse($group['rows'] as $kpi)
                <tr>
                    <td>{{ $kpi->kra_name }}</td>
                    <td>{{ $kpi->performance_measure }}</td>
                    <td class="text-right">{{ $kpi->target ?: '—' }}</td>
                    <td class="text-right">{{ $kpi->actual_achieved ?: '—' }}</td>
                    <td class="text-right">
                        {{ $kpi->target_percent !== null
                            ? rtrim(rtrim(number_format($kpi->target_percent, 2), '0'), '.') . '%'
                            : '—' }}
                    </td>
                    <td class="text-right">{{ $kpi->rating ?? '—' }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($kpi->weightage, 2), '0'), '.') }}%</td>
                    <td class="text-right">{{ $kpi->weighted_index !== null ? number_format($kpi->weighted_index, 2) : '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="color:#94a3b8">No KPIs recorded under this perspective.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <table style="margin-top:14px">
        <tr class="total-row">
            <td><strong>OVERALL PERFORMANCE RATING</strong></td>
            <td class="text-right">{{ rtrim(rtrim(number_format($appraisal->totalWeight(), 2), '0'), '.') }}%</td>
            <td class="text-right">{{ number_format($appraisal->overall_index ?? 0, 2) }}</td>
            <td class="text-right">{{ number_format($appraisal->overall_percent ?? 0, 2) }}%</td>
            <td class="text-right">{{ $appraisal->bandLabel() }}</td>
        </tr>
    </table>

    {{-- The rating scale this card was actually marked on, printed with it - a
         3 means nothing on paper without knowing whether the scale ran to 5 or
         to 10. --}}
    @if($scale)
    <p style="margin-top:10px; font-size:10px; color:#64748b">
        Rated on <strong>{{ $scale->name }}</strong> (out of {{ $scale->max_points }}):
        @foreach($scale->bands as $band){{ $band->points }} = {{ $band->label }}@if(!$loop->last) · @endif @endforeach
    </p>
    @endif

    <table style="margin-top:16px">
        <tr>
            <td style="width:50%; vertical-align:top">
                <strong>Employee's Comments</strong>
                <p style="margin-top:4px">{{ $appraisal->employee_comment ?: '—' }}</p>
                <p style="margin-top:10px; font-size:10px; color:#64748b">
                    Signed: {{ $appraisal->employee_signed_at?->format('d M Y H:i') ?? 'Not signed' }}
                </p>
            </td>
            <td style="width:50%; vertical-align:top">
                <strong>Manager's Comments</strong>
                <p style="margin-top:4px">{{ $appraisal->manager_comment ?: '—' }}</p>
                <p style="margin-top:10px; font-size:10px; color:#64748b">
                    Confirmed: {{ $appraisal->manager_signed_at?->format('d M Y H:i') ?? 'Not confirmed' }}
                </p>
            </td>
        </tr>
    </table>

    <p style="margin-top:22px; font-size:9px; color:#94a3b8">
        Generated {{ now()->format('d M Y H:i') }} · Mastermind Consultants HR System
    </p>
</div>
@endsection
