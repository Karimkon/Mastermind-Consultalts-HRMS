@extends("layouts.app")
@section("title","Apply for Leave")
@section("content")
<x-page-header title="Apply for Leave">
    <a href="{{ route('leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if($employee)
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card p-6">
        <form method="POST" action="{{ route('leaves.store') }}" class="space-y-5">@csrf

            {{-- Leave type --}}
            <div>
                <label class="form-label">Leave Type <span class="text-red-500">*</span></label>
                <select name="leave_type_id" class="form-select" required onchange="updateBalance(this.value)">
                    <option value="">Select leave type</option>
                    @foreach($leaveTypes as $lt)
                    <option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->days_allowed }} days/year)</option>
                    @endforeach
                </select>
            </div>
            <div id="balanceInfo" class="hidden bg-blue-50 rounded-lg p-3 text-sm text-blue-700"></div>

            {{-- Dates --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">From Date <span class="text-red-500">*</span></label>
                    <input type="date" name="from_date" id="fromDate" class="form-input" min="{{ date('Y-m-d') }}" required onchange="calcDays()">
                </div>
                <div>
                    <label class="form-label">To Date <span class="text-red-500">*</span></label>
                    <input type="date" name="to_date" id="toDate" class="form-input" min="{{ date('Y-m-d') }}" required onchange="calcDays()">
                </div>
            </div>
            <div id="daysInfo" class="hidden bg-green-50 rounded-lg p-3 text-sm text-green-700"></div>

            {{-- Reason --}}
            <div>
                <label class="form-label">Reason <span class="text-red-500">*</span></label>
                <textarea name="reason" class="form-input" rows="3" required
                          placeholder="Please provide a reason for your leave request...">{{ old('reason') }}</textarea>
            </div>

            {{-- Replacement person (compulsory) --}}
            <div class="border border-amber-200 bg-amber-50 rounded-xl p-5">
                <h3 class="text-sm font-semibold text-amber-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-user-friends text-amber-600"></i>
                    Replacement / Cover Person <span class="text-red-500">*</span>
                </h3>
                <p class="text-xs text-amber-700 mb-4">
                    You must provide the contact details of the person who will cover your responsibilities during your absence.
                    This information is sent to your Account Manager and Client for approval.
                </p>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="replacement_name" class="form-input"
                               value="{{ old('replacement_name') }}"
                               placeholder="Full name of replacement person" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="replacement_email" class="form-input"
                                   value="{{ old('replacement_email') }}"
                                   placeholder="replacement@email.com" required>
                        </div>
                        <div>
                            <label class="form-label">Phone <span class="text-red-500">*</span></label>
                            <input type="tel" name="replacement_phone" class="form-input"
                                   value="{{ old('replacement_phone') }}"
                                   placeholder="+256 700 000000" required>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full">
                <i class="fas fa-paper-plane mr-1"></i> Submit Leave Request
            </button>

            <p class="text-xs text-slate-400 text-center">
                An email notification will be sent to your <strong>Account Manager</strong> and <strong>Client</strong> upon submission.
            </p>
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
                    <div class="bg-blue-500 h-1.5 rounded-full"
                         style="width: {{ $bal->total_days > 0 ? min(100, ($bal->used_days/$bal->total_days)*100) : 0 }}%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1">{{ $bal->used_days }}/{{ $bal->total_days }} days used</p>
            </div>
            @endforeach
        </div>

        <div class="mt-5 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>Leave approval flow:</strong><br>
            1. You submit with replacement details.<br>
            2. Email sent to Account Manager + Client.<br>
            3. Account Manager reviews first.<br>
            4. Client gives final approval.
        </div>
    </div>
</div>
@else
<div class="card p-6 text-center text-slate-400">
    No employee profile linked to your account. Contact HR.
</div>
@endif
@endsection

@push("scripts")
<script>
const balances = @json($balances->keyBy('leave_type_id'));

function calcDays() {
    const from = document.getElementById("fromDate").value;
    const to   = document.getElementById("toDate").value;
    if (from && to) {
        const days = Math.ceil((new Date(to) - new Date(from)) / (1000*60*60*24)) + 1;
        document.getElementById("daysInfo").innerHTML =
            `<i class="fas fa-info-circle mr-2"></i>${days} day(s) requested`;
        document.getElementById("daysInfo").classList.remove("hidden");
    }
}

function updateBalance(typeId) {
    const info = document.getElementById('balanceInfo');
    if (balances[typeId]) {
        const b = balances[typeId];
        info.innerHTML = `<i class="fas fa-piggy-bank mr-2"></i>Balance: <strong>${b.remaining} days</strong> remaining (${b.used_days}/${b.total_days} used)`;
        info.classList.remove('hidden');
    } else {
        info.classList.add('hidden');
    }
}
</script>
@endpush
