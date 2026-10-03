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
        {{-- Meeting papers. Visible to the organizer and the people invited;
             the download route checks the same thing, because hiding a link is
             not the same as refusing the file. --}}
        @php
            // A BLOCK, not inline @php(...): Blade matches the inline form with a
            // simple paren matcher, and an expression carrying nested calls and
            // ?-> breaks it — it compiles to a bare <?php( and silently swallows
            // the directive after it.
            $isAdmin = auth()->user()->hasAnyRole(['super-admin', 'hr-admin']);
            $canSeeFiles = $meeting->involves(auth()->user()) || $isAdmin;
            $isOrganiser = $meeting->organizer_id === auth()->user()->employee?->id || $isAdmin;
        @endphp

        @if($canSeeFiles)
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">
                <i class="fas fa-paperclip mr-1 text-slate-400"></i>
                Documents ({{ $meeting->files->count() }})
            </h3>

            <div class="divide-y divide-slate-100">
                @forelse($meeting->files as $f)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ $f->original_name }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $f->readable_size }}
                            @if($f->uploader) &middot; {{ $f->uploader->name }} @endif
                            &middot; {{ $f->created_at->format('d M Y H:i') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('meetings.files.download', [$meeting, $f]) }}"
                           data-download-progress data-filename="{{ $f->original_name }}"
                           class="text-sm text-emerald-600 font-medium whitespace-nowrap">
                            <i class="fas fa-download mr-1"></i>Open
                        </a>
                        @if($isOrganiser)
                        <form method="POST" action="{{ route('meetings.files.destroy', [$meeting, $f]) }}"
                              onsubmit="return confirm('Remove {{ $f->original_name }} from this meeting?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-600">Remove</button>
                        </form>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-sm text-slate-400 py-2">No documents attached.</p>
                @endforelse
            </div>

            @if($isOrganiser)
            <form method="POST" action="{{ route('meetings.files.store', $meeting) }}"
                  enctype="multipart/form-data" class="border-t border-slate-100 pt-4 mt-3 space-y-3"
                  data-upload-progress>
                @csrf
                <x-file-drop name="files[]" label="Attach documents" :multiple="true" :required="true" />
                <div class="flex justify-end">
                    <button class="btn-secondary"><i class="fas fa-cloud-arrow-up mr-1"></i> Attach</button>
                </div>
            </form>
            @endif
        </div>
        @endif
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
<x-transfer-progress />

@endsection