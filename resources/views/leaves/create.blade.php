@extends("layouts.app")
@section("title","Apply for Leave")
@section("content")
<x-page-header title="Apply for Leave">
    <a href="{{ route('leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

@if($employee)
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card p-6">
        <form method="POST" action="{{ route('leaves.store') }}" class="space-y-4">@csrf
            <div><label class="form-label">Leave Type *</label>
                <select name="leave_type_id" class="form-select" required onchange="updateBalance(this.value)">
                    <option value="">Select leave type</option>
                    @foreach($leaveTypes as $lt)<option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->days_allowed }} days/year)</option>@endforeach
                </select>
            </div>
            <div id="balanceInfo" class="hidden bg-blue-50 rounded-lg p-3 text-sm text-blue-700"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="form-label">From Date *</label><input type="date" name="from_date" id="fromDate" class="form-input" min="{{ date('Y-m-d') }}" required onchange="calcDays()"></div>
                <div><label class="form-label">To Date *</label><input type="date" name="to_date" id="toDate" class="form-input" min="{{ date('Y-m-d') }}" required onchange="calcDays()"></div>
            </div>
            <div id="daysInfo" class="hidden bg-green-50 rounded-lg p-3 text-sm text-green-700"></div>
            <div><label class="form-label">Reason *</label><textarea name="reason" class="form-input" rows="4" required placeholder="Please provide a reason for your leave request..."></textarea></div>
            <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
        </form>
    </div>

    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">My Leave Balances</h3>
        <div class="space-y-3" id="balanceList">
            @foreach($employee->leaveBalances->where('year', date('Y')) as $bal)
            <div class="rounded-lg p-3 bg-slate-50 border border-slate-200" data-leave="{{ $bal->leave_type_id }}">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ $bal->leaveType->name }}</span>
                    <span class="text-sm font-bold text-blue-600">{{ $bal->remaining }} left</span>
                </div>
                <div class="bg-slate-200 rounded-full h-1.5">
                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $bal->total_days > 0 ? min(100, ($bal->used_days/$bal->total_days)*100) : 0 }}%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1">{{ $bal->used_days }}/{{ $bal->total_days }} days used</p>
            </div>
            @endforeach
        </div>
    </div>
</div>
@else
<div class="card p-6 text-center text-slate-400">No employee profile linked to your account. Contact HR.</div>
@endif
@endsection
@push("scripts")
<script>
function calcDays() {
    const from = document.getElementById("fromDate").value, to = document.getElementById("toDate").value;
    if (from && to) {
        const days = Math.ceil((new Date(to) - new Date(from)) / (1000*60*60*24)) + 1;
        document.getElementById("daysInfo").innerHTML = `<i class="fas fa-info-circle mr-2"></i>${days} working day(s) requested`;
        document.getElementById("daysInfo").classList.remove("hidden");
    }
}
</script>
@endpush
