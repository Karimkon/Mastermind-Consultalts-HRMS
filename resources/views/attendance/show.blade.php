@extends("layouts.app")
@section("title","Attendance Record")
@section("content")
<x-page-header title="Attendance Record">
    <a href="{{ route('attendance.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="card p-6 max-w-md">
    <dl class="space-y-3">
        @foreach(["Employee" => $attendance->employee->full_name, "Date" => $attendance->date->format('M d, Y'), "Clock In" => $attendance->clock_in?->format('H:i'), "Clock Out" => $attendance->clock_out?->format('H:i'), "Status" => ucfirst($attendance->status), "Overtime" => $attendance->overtime_hours . 'h', "Note" => $attendance->note ?? '—'] as $label => $val)
        <div class="flex justify-between py-2 border-b border-slate-100">
            <dt class="text-sm text-slate-500">{{ $label }}</dt>
            <dd class="text-sm font-medium text-slate-800">{{ $val }}</dd>
        </div>
        @endforeach
    </dl>
</div>
@endsection
