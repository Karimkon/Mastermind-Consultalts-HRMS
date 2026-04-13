@extends("layouts.app")
@section("title","Edit Payroll Run")
@section("content")
<x-page-header title="Edit Payroll Run"><a href="{{ route('payroll.show',$payroll) }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></x-page-header>
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('payroll.update',$payroll) }}" class="space-y-4">@csrf @method("PUT")
        <div><label class="form-label">Notes</label><textarea name="notes" class="form-input" rows="3">{{ $payroll->notes }}</textarea></div>
        <div><label class="form-label">Payment Date</label><input type="date" name="payment_date" value="{{ $payroll->payment_date?->format('Y-m-d') }}" class="form-input"></div>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save</button>
    </form>
</div>
@endsection
