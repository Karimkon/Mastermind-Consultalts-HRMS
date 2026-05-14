@extends("layouts.app")
@section("title","New Payroll Run")
@section("content")
<x-page-header title="Create Payroll Run">
    <a href="{{ route('payroll.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card p-6">
        <form method="POST" action="{{ route('payroll.store') }}" class="space-y-5">@csrf

            <div>
                <label class="form-label">Client Company</label>
                <select name="client_id" class="form-select">
                    <option value="">— General (no specific client) —</option>
                    @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}
                            data-pay-day="{{ $c->payment_day }}">
                        {{ $c->company_name }}
                        @if($c->payment_day) (Pay day: {{ $c->payment_day }})@endif
                    </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Select the client company this payroll run is for.</p>
                <div id="payDayHint" class="hidden mt-2 p-2 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700">
                    <i class="fas fa-calendar-check mr-1"></i>
                    Contract payment day: <strong id="payDayLabel"></strong>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Month *</label>
                    <select name="month" class="form-select" required>
                        @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ date('n') == $m ? 'selected' : '' }}>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="form-label">Year *</label>
                    <select name="year" class="form-select" required>
                        @for($y = date('Y'); $y >= date('Y')-3; $y--)
                        <option value="{{ $y }}" {{ date('Y') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div>
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-input" rows="3" placeholder="Optional notes for this payroll run...">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="btn-primary w-full">
                <i class="fas fa-plus mr-1"></i> Create Payroll Run
            </button>
        </form>
    </div>

    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <i class="fas fa-info-circle text-slate-400"></i> How It Works
        </h3>
        <ul class="space-y-3 text-sm text-slate-600">
            <li class="flex gap-2"><span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">1</span>Select the client company this payroll is for (or leave blank for general payroll).</li>
            <li class="flex gap-2"><span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">2</span>Process the run — the system calculates all payslips based on attendance and salary setup.</li>
            <li class="flex gap-2"><span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">3</span>Approve the run after review.</li>
            <li class="flex gap-2"><span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-bold flex-shrink-0">4</span>Mark as Paid — this automatically <strong>locks</strong> the payroll run.</li>
            <li class="flex gap-2"><span class="w-6 h-6 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xs font-bold flex-shrink-0"><i class="fas fa-lock text-xs"></i></span>Once locked, only a <strong>Super Admin</strong> can unlock and edit the run.</li>
        </ul>

        @if($clients->isNotEmpty())
        <div class="mt-5">
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Client Payment Schedule</h4>
            <div class="space-y-2">
                @foreach($clients->where('payment_day', '!=', null) as $c)
                <div class="flex justify-between items-center text-sm py-1.5 border-b border-slate-100">
                    <span class="text-slate-700">{{ $c->company_name }}</span>
                    <span class="text-blue-600 font-medium">
                        <i class="fas fa-calendar-day mr-1"></i>{{ $c->payment_day }}{{ ['th','st','nd','rd'][min(3, $c->payment_day % 10)] }} of month
                    </span>
                </div>
                @endforeach
                @if($clients->whereNull('payment_day')->isNotEmpty())
                <p class="text-xs text-slate-400 mt-1">{{ $clients->whereNull('payment_day')->count() }} client(s) without a set payment day.</p>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.querySelector('select[name="client_id"]').addEventListener('change', function () {
    const sel  = this.options[this.selectedIndex];
    const day  = sel.dataset.payDay;
    const hint = document.getElementById('payDayHint');
    if (day) {
        document.getElementById('payDayLabel').textContent = day + getOrdinal(day) + ' of every month';
        hint.classList.remove('hidden');
    } else {
        hint.classList.add('hidden');
    }
});
function getOrdinal(n) {
    const s = ['th','st','nd','rd'], v = n % 100;
    return s[(v - 20) % 10] || s[v] || s[0];
}
</script>
@endpush
@endsection
