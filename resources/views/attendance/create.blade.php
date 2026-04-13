@extends("layouts.app")
@section("title","Mark Attendance")
@section("content")
<x-page-header title="Mark Attendance" subtitle="Record employee attendance manually">
    <a href="{{ route('attendance.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('attendance.store') }}" class="space-y-4">
        @csrf
        <div><label class="form-label">Employee *</label><select name="employee_id" class="form-select select2-ajax-employees" required></select></div>
        <div><label class="form-label">Date *</label><input type="date" name="date" value="{{ date('Y-m-d') }}" class="form-input" required></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Clock In</label><input type="time" name="clock_in" class="form-input"></div>
            <div><label class="form-label">Clock Out</label><input type="time" name="clock_out" class="form-input"></div>
        </div>
        <div><label class="form-label">Status *</label>
            <select name="status" class="form-select" required>
                @foreach(["present"=>"Present","absent"=>"Absent","late"=>"Late","half_day"=>"Half Day","holiday"=>"Holiday","weekend"=>"Weekend"] as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <div><label class="form-label">Note</label><textarea name="note" class="form-input" rows="2"></textarea></div>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Attendance</button>
    </form>
</div>
@endsection
