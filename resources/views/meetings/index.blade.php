@extends('layouts.app')
@section('title', 'Meetings')
@section('content')
<x-page-header title="Meetings" subtitle="Scheduled meetings and events">
    <a href="{{ route('meetings.calendar') }}" class="btn-secondary"><i class="fas fa-calendar mr-1"></i> Calendar</a>
    <a href="{{ route('meetings.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New Meeting</a>
</x-page-header>

<x-filter-bar :action="route('meetings.index')">
    <div class="flex-1"><input type="text" name="search" value="{{ request('search') }}" placeholder="Search title..." class="form-input w-full"></div>
    <div class="w-36"><select name="status" class="form-input w-full"><option value="">All Status</option>@foreach(['scheduled','ongoing','completed','cancelled'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
</x-filter-bar>

<div class="space-y-3">
    @forelse($meetings as $meeting)
    <div class="card p-5 flex items-start gap-4 hover:shadow-md transition-shadow">
        <div class="text-center w-14 flex-shrink-0">
            <div class="bg-blue-600 text-white rounded-t-lg py-1 text-xs font-medium">{{ \Carbon\Carbon::parse($meeting->start_at)->format('M') }}</div>
            <div class="bg-blue-50 rounded-b-lg py-1 text-xl font-bold text-blue-800">{{ \Carbon\Carbon::parse($meeting->start_at)->format('d') }}</div>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold text-slate-800">{{ $meeting->title }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5"><i class="fas fa-clock mr-1"></i>{{ \Carbon\Carbon::parse($meeting->start_at)->format('H:i') }} – {{ \Carbon\Carbon::parse($meeting->end_at)->format('H:i') }} @if($meeting->location)<span class="ml-2"><i class="fas fa-map-marker-alt mr-1"></i>{{ $meeting->location }}</span>@endif</p>
                </div>
                <span class="badge {{ $meeting->status === 'completed' ? 'badge-green' : ($meeting->status === 'cancelled' ? 'badge-red' : 'badge-blue') }} ml-2 flex-shrink-0">{{ ucfirst($meeting->status) }}</span>
            </div>
            <div class="flex items-center gap-2 mt-2">
                <p class="text-xs text-slate-500">Organizer: {{ $meeting->organizer->full_name ?? '—' }}</p>
                <span class="text-slate-300">|</span>
                <p class="text-xs text-slate-500"><i class="fas fa-users mr-1"></i>{{ $meeting->participants->count() }} participants</p>
            </div>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <a href="{{ route('meetings.show', $meeting) }}" class="btn-xs btn-blue">View</a>
        </div>
    </div>
    @empty
    <div class="card p-12 text-center text-slate-400">
        <i class="fas fa-calendar-times text-4xl mb-3 opacity-30 block"></i>
        <p>No meetings scheduled.</p>
    </div>
    @endforelse
</div>
<div class="mt-4">{{ $meetings->withQueryString()->links() }}</div>
@endsection