@extends('layouts.app')
@section('title', $meeting->title)
@section('content')
<x-page-header :title="$meeting->title" subtitle="Meeting Details">
    <a href="{{ route('meetings.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
    @if($meeting->organizer_id === auth()->user()->employee?->id)
    <a href="{{ route('meetings.edit', $meeting) }}" class="btn-secondary"><i class="fas fa-edit mr-1"></i> Edit</a>
    @endif
</x-page-header>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6">
            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                <div><span class="text-slate-500">Start</span><p class="font-medium">{{ \Carbon\Carbon::parse($meeting->start_at)->format('D, M d Y H:i') }}</p></div>
                <div><span class="text-slate-500">End</span><p class="font-medium">{{ \Carbon\Carbon::parse($meeting->end_at)->format('D, M d Y H:i') }}</p></div>
                <div><span class="text-slate-500">Location</span><p class="font-medium">{{ $meeting->location ?? '—' }}</p></div>
                <div><span class="text-slate-500">Organizer</span><p class="font-medium">{{ $meeting->organizer->full_name ?? '—' }}</p></div>
                <div><span class="text-slate-500">Status</span><span class="badge badge-blue">{{ ucfirst($meeting->status) }}</span></div>
                @if($meeting->recurrence)
                <div><span class="text-slate-500">Recurrence</span>
                    <p class="font-medium capitalize">{{ str_replace('biweekly','Every 2 weeks',$meeting->recurrence) }}
                    @if($meeting->recurrence_end_date) <span class="text-slate-400 text-xs">until {{ $meeting->recurrence_end_date->format('M d, Y') }}</span>@endif</p>
                </div>
                @endif
                @if($meeting->parent_meeting_id)
                <div><span class="text-slate-500">Series</span>
                    <a href="{{ route('meetings.show', $meeting->parent_meeting_id) }}" class="text-xs text-blue-600 hover:underline">View parent meeting</a>
                </div>
                @endif
            </div>
            @if($meeting->description)
            <div class="border-t border-slate-100 pt-4">
                <h4 class="text-sm font-semibold text-slate-700 mb-1">Description</h4>
                <p class="text-sm text-slate-600">{{ $meeting->description }}</p>
            </div>
            @endif
        </div>
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Participants ({{ $meeting->participants->count() }})</h3>
            <div class="space-y-2">
                @forelse($meeting->participants as $p)
                <div class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
                    <div class="flex items-center gap-3">
                        <img src="{{ $p->employee->avatar_url ?? '' }}" class="w-8 h-8 rounded-full object-cover">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $p->employee->full_name ?? '—' }}</p>
                            <p class="text-xs text-slate-500">{{ $p->employee->designation->title ?? '' }}</p>
                        </div>
                    </div>
                    @php $rsvpColors = ['accepted'=>'badge-green','declined'=>'badge-red','pending'=>'badge-yellow']; @endphp
                    <span class="badge {{ $rsvpColors[$p->rsvp] ?? 'badge-slate' }}">{{ ucfirst($p->rsvp) }}</span>
                </div>
                @empty
                <p class="text-sm text-slate-400">No participants.</p>
                @endforelse
            </div>
            @php $myParticipation = $meeting->participants->where('employee_id', auth()->user()->employee?->id)->first(); @endphp
            @if($myParticipation)
            <div class="mt-4 pt-4 border-t border-slate-100">
                <p class="text-xs text-slate-500 mb-2">Your RSVP: <span class="font-medium">{{ ucfirst($myParticipation->rsvp) }}</span></p>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('meetings.rsvp', $meeting) }}">@csrf<input type="hidden" name="rsvp" value="accepted"><button type="submit" class="btn-secondary text-xs text-green-600"><i class="fas fa-check mr-1"></i>Accept</button></form>
                    <form method="POST" action="{{ route('meetings.rsvp', $meeting) }}">@csrf<input type="hidden" name="rsvp" value="declined"><button type="submit" class="btn-secondary text-xs text-red-600"><i class="fas fa-times mr-1"></i>Decline</button></form>
                </div>
            </div>
            @endif
        </div>
    </div>
    <div>
        @if($meeting->organizer_id === auth()->user()->employee?->id && $meeting->status !== 'cancelled')
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Actions</h3>
            <form method="POST" action="{{ route('meetings.cancel', $meeting) }}" onsubmit="return confirm('Cancel this meeting?')">
                @csrf
                <button type="submit" class="btn-secondary w-full text-red-600 text-sm"><i class="fas fa-ban mr-1"></i> Cancel Meeting</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection