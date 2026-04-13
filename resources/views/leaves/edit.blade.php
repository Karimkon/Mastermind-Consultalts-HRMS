@extends("layouts.app")
@section("title","Edit Leave Request")
@section("content")
<x-page-header title="Edit Leave Request">
    <a href="{{ route('leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('leaves.update',$leave) }}" class="space-y-4">@csrf @method("PUT")
        <div><label class="form-label">From Date</label><input type="date" name="from_date" value="{{ $leave->from_date->format('Y-m-d') }}" class="form-input" required></div>
        <div><label class="form-label">To Date</label><input type="date" name="to_date" value="{{ $leave->to_date->format('Y-m-d') }}" class="form-input" required></div>
        <div><label class="form-label">Reason</label><textarea name="reason" class="form-input" rows="3" required>{{ $leave->reason }}</textarea></div>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Request</button>
    </form>
</div>
@endsection
