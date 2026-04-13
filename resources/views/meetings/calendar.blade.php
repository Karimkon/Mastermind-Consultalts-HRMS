@extends('layouts.app')
@section('title', 'Meeting Calendar')
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
@endpush
@section('content')
<x-page-header title="Meeting Calendar">
    <a href="{{ route('meetings.index') }}" class="btn-secondary"><i class="fas fa-list mr-1"></i> List View</a>
    <a href="{{ route('meetings.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New Meeting</a>
</x-page-header>

<div class="card p-4">
    <div id="calendar"></div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cal = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
        height: 'auto',
        events: @json($calendarEvents),
        eventClick: function(info) {
            window.location = '/meetings/' + info.event.id;
        },
        eventColor: '#1e40af',
    });
    cal.render();
});
</script>
@endpush
@endsection