@extends("layouts.app")
@section("title","Edit Attendance")
@section("content")
<x-page-header title="Edit Attendance Record">
    <a href="{{ route('attendance.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="card p-6 max-w-md">
    <form method="POST" action="{{ route('attendance.update',$attendance) }}" class="space-y-4">@csrf @method("PUT")
        <div><label class="form-label">Clock In</label><input type="datetime-local" name="clock_in" value="{{ $attendance->clock_in?->format('Y-m-d\TH:i') }}" class="form-input"></div>
        <div><label class="form-label">Clock Out</label><input type="datetime-local" name="clock_out" value="{{ $attendance->clock_out?->format('Y-m-d\TH:i') }}" class="form-input"></div>
        <div><label class="form-label">Status</label><select name="status" class="form-select">@foreach(["present"=>"Present","absent"=>"Absent","late"=>"Late","half_day"=>"Half Day"] as $v=>$l)<option value="{{ $v }}" {{ $attendance->status==$v?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
        <div><label class="form-label">Note</label><textarea name="note" class="form-input" rows="2">{{ $attendance->note }}</textarea></div>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update</button>
    </form>
</div>
@endsection
