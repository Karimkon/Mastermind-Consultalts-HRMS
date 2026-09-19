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
                    Search for the colleague who will cover your responsibilities while you are away.
                    They are told straight away that you have nominated them, and your Account Manager
                    and Client see it when they review this request.
                </p>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Who will cover for you? <span class="text-red-500">*</span></label>

                        {{-- Picked from the staff register, not typed. A typed name
                             could not be notified, could not see the request, and
                             quietly disagreed with Employee Central about spelling. --}}
                        <select name="replacement_employee_id"
                                class="select2-ajax-employees w-full"
                                data-exclude-self="1" required>
                            @if(old('replacement_employee_id'))
                                @php($picked = \App\Models\Employee::find(old('replacement_employee_id')))
                                @if($picked)
                                    <option value="{{ $picked->id }}" selected>
                                        {{ $picked->full_name }} ({{ $picked->emp_number }})
                                    </option>
                                @endif
                            @endif
                        </select>

                        <p class="text-xs text-amber-700 mt-1">
                            Search by name or staff number. Their email is taken from their staff record.
                        </p>
                        @error('replacement_employee_id')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Optional overrides. Their record is the default; these exist
                         so a stale address can be corrected without an admin, and
                         so the submitter is told rather than the mail failing
                         silently at send time. --}}
                    <details class="text-xs" {{ $errors->has('replacement_email') || $errors->has('replacement_phone') ? 'open' : '' }}>
                        <summary class="cursor-pointer text-amber-800 font-medium select-none">
                            Use different contact details for them
                        </summary>
                        <div class="grid grid-cols-2 gap-3 mt-3">
                            <div>
                                <label class="form-label">Email</label>
                                <input type="email" name="replacement_email" class="form-input"
                                       value="{{ old('replacement_email') }}"
                                       placeholder="Leave blank to use their staff record">
                                @error('replacement_email')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Phone</label>
                                <input type="tel" name="replacement_phone" class="form-input"
                                       value="{{ old('replacement_phone') }}"
                                       placeholder="Leave blank to use their staff record">
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full">
                <i class="fas fa-paper-plane mr-1"></i> Submit Leave Request
            </button>

            <p class="text-xs text-slate-400 text-center">
                On submission an email goes to your <strong>Account Manager</strong>, your <strong>Client</strong>,
                and the <strong>colleague you nominated</strong>.
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
