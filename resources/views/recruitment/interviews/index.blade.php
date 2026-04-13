@extends('layouts.app')
@section('title', 'Interviews')
@section('content')
<x-page-header title="Interview Schedule" subtitle="Manage candidate interviews">
    <a href="{{ route('recruitment.interviews.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> Schedule Interview</a>
</x-page-header>

<x-data-table>
    <thead><tr class="table-header"><th>Candidate</th><th>Job</th><th>Type</th><th>Scheduled</th><th>Interviewer</th><th>Rating</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($interviews as $iv)
    <tr class="table-row">
        <td class="px-4 py-3 font-medium text-slate-800">{{ $iv->candidate->name ?? '—' }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $iv->candidate->jobPosting->title ?? '—' }}</td>
        <td class="px-4 py-3 text-sm">{{ ucfirst($iv->type) }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ \Carbon\Carbon::parse($iv->scheduled_at)->format('M d, Y H:i') }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $iv->interviewer->full_name ?? '—' }}</td>
        <td class="px-4 py-3">@if($iv->rating)<div class="flex">@for($r=1;$r<=5;$r++)<i class="fas fa-star text-xs {{ $r<=$iv->rating ? 'text-amber-400' : 'text-slate-200' }}"></i>@endfor</div>@else<span class="text-xs text-slate-400">Pending</span>@endif</td>
        <td class="px-4 py-3"><span class="badge badge-blue">{{ ucfirst($iv->status) }}</span></td>
        <td class="px-4 py-3"><a href="{{ route('recruitment.interviews.show', $iv) }}" class="btn-xs btn-blue">View</a></td>
    </tr>
    @empty
    <tr><td colspan="8" class="text-center py-12 text-slate-400">No interviews scheduled.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $interviews->withQueryString()->links() }}</div>
@endsection