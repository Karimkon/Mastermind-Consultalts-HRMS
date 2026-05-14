@extends('layouts.app')
@section('title', 'Probation: ' . $employee->full_name)
@section('content')
<x-page-header title="Probation Review">
    <a href="{{ route('probation.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if(session('success'))
<div class="alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert-error mb-4">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- LEFT: Employee Profile + Timeline --}}
    <div class="space-y-4">
        {{-- Profile Card --}}
        <div class="card p-5 text-center">
            <img src="{{ $employee->avatar_url }}" class="w-20 h-20 rounded-2xl object-cover mx-auto mb-3 border-2 border-slate-200">
            <h3 class="font-bold text-slate-900 text-lg">{{ $employee->full_name }}</h3>
            <p class="text-sm text-slate-500">{{ $employee->designation?->title }}</p>
            <p class="text-xs text-slate-400">{{ $employee->department?->name }}</p>
            <div class="mt-3 pt-3 border-t border-slate-100">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-slate-500">Hire Date</span>
                    <span class="font-semibold">{{ $employee->hire_date->format('d M Y') }}</span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-slate-500">Employment</span>
                    <span class="font-semibold capitalize">{{ str_replace('_', ' ', $employee->employment_type) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Employee #</span>
                    <span class="font-semibold font-mono">{{ $employee->emp_number }}</span>
                </div>
            </div>
        </div>

        {{-- Status Card --}}
        <div class="card p-5 text-center">
            <p class="text-xs text-slate-500 uppercase tracking-wide mb-2">Probation Status</p>
            <div class="text-2xl mb-2">{!! $employee->probation_status_badge !!}</div>
            @if($employee->probation_end_date)
            @php $daysLeft = now()->diffInDays($employee->probation_end_date, false); @endphp
            <p class="text-sm text-slate-600 mt-2">
                End date: <strong>{{ $employee->probation_end_date->format('d M Y') }}</strong>
            </p>
            @if($daysLeft >= 0)
            <p class="text-sm font-semibold mt-1 {{ $daysLeft <= 7 ? 'text-red-600' : ($daysLeft <= 30 ? 'text-amber-600' : 'text-slate-600') }}">
                {{ (int)$daysLeft }} days remaining
            </p>
            @else
            <p class="text-sm font-semibold text-red-600 mt-1">{{ abs((int)$daysLeft) }} days overdue</p>
            @endif
            @endif
            @if($employee->probation_confirmed_at)
            <p class="text-xs text-slate-400 mt-2">
                Confirmed on {{ $employee->probation_confirmed_at->format('d M Y') }}
                @if($employee->probationConfirmedBy) by {{ $employee->probationConfirmedBy->name }} @endif
            </p>
            @endif
        </div>

        {{-- Timeline --}}
        <div class="card p-5">
            <h4 class="font-semibold text-slate-700 mb-4 text-sm uppercase tracking-wide">Timeline</h4>
            <div class="relative pl-5">
                <div class="absolute left-1.5 top-0 bottom-0 w-0.5 bg-slate-200"></div>
                {{-- Hire Date --}}
                <div class="relative mb-4">
                    <div class="absolute -left-5 top-0.5 w-3 h-3 rounded-full bg-blue-500 border-2 border-white"></div>
                    <p class="text-xs font-semibold text-blue-600">Hire Date</p>
                    <p class="text-sm text-slate-700 font-bold">{{ $employee->hire_date->format('d M Y') }}</p>
                </div>
                {{-- Probation End --}}
                @if($employee->probation_end_date)
                <div class="relative mb-4">
                    <div class="absolute -left-5 top-0.5 w-3 h-3 rounded-full {{ $employee->probation_end_date->isPast() ? 'bg-red-500' : 'bg-amber-500' }} border-2 border-white"></div>
                    <p class="text-xs font-semibold {{ $employee->probation_end_date->isPast() ? 'text-red-600' : 'text-amber-600' }}">Probation End</p>
                    <p class="text-sm text-slate-700 font-bold">{{ $employee->probation_end_date->format('d M Y') }}</p>
                </div>
                @endif
                {{-- Confirmed --}}
                @if($employee->probation_confirmed_at)
                <div class="relative">
                    <div class="absolute -left-5 top-0.5 w-3 h-3 rounded-full bg-green-500 border-2 border-white"></div>
                    <p class="text-xs font-semibold text-green-600">Outcome Confirmed</p>
                    <p class="text-sm text-slate-700 font-bold">{{ $employee->probation_confirmed_at->format('d M Y') }}</p>
                    <p class="text-xs text-slate-400 capitalize">{{ $employee->probation_status }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- RIGHT: Actions --}}
    <div class="lg:col-span-2 space-y-4">

        {{-- Set/Update Probation End Date --}}
        @unless(in_array($employee->probation_status, ['passed', 'failed']))
        <div class="card p-5">
            <h4 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-plus text-blue-400"></i>
                {{ $employee->probation_end_date ? 'Update' : 'Set' }} Probation End Date
            </h4>
            <form method="POST" action="{{ route('probation.set-end', $employee) }}">
                @csrf
                <div class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Probation End Date</label>
                        <input type="date" name="probation_end_date" class="form-input"
                               value="{{ old('probation_end_date', $employee->probation_end_date?->format('Y-m-d')) }}"
                               min="{{ $employee->hire_date->addDays(1)->format('Y-m-d') }}" required>
                        @error('probation_end_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
                <p class="text-xs text-slate-400 mt-2">
                    <i class="fas fa-info-circle mr-1"></i> Standard probation period is typically 3–6 months from hire date.
                </p>
            </form>
        </div>
        @endunless

        {{-- Confirm Outcome --}}
        @if($employee->probation_end_date && !in_array($employee->probation_status, ['passed', 'failed']))
        <div class="card p-5">
            <h4 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fas fa-gavel text-amber-400"></i> Confirm Probation Outcome
            </h4>
            <form method="POST" action="{{ route('probation.confirm', $employee) }}" id="confirmForm">
                @csrf
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Outcome <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach(['passed' => ['Passed', 'green', 'fa-check-circle'], 'extended' => ['Extended', 'blue', 'fa-clock'], 'failed' => ['Failed', 'red', 'fa-times-circle']] as $val => [$label, $color, $icon])
                        <label class="cursor-pointer">
                            <input type="radio" name="outcome" value="{{ $val }}" class="sr-only outcome-radio"
                                   {{ old('outcome') === $val ? 'checked' : '' }}>
                            <div class="border-2 border-slate-200 rounded-xl p-3 text-center hover:border-{{ $color }}-300 transition-colors outcome-btn">
                                <i class="fas {{ $icon }} text-{{ $color }}-400 text-xl mb-1"></i>
                                <p class="text-sm font-bold text-slate-700">{{ $label }}</p>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Extended date field --}}
                <div id="extendedDateField" class="hidden mb-4">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">New Probation End Date <span class="text-red-500">*</span></label>
                    <input type="date" name="new_probation_end_date" class="form-input">
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Notes / Remarks</label>
                    <textarea name="notes" class="form-input" rows="3" placeholder="Reason for decision, feedback, etc.">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn-primary" id="confirmBtn" disabled>
                    <i class="fas fa-check-double mr-1"></i> Confirm Outcome
                </button>
            </form>
        </div>
        @endif

        {{-- History / Bio Notes --}}
        @if($employee->bio)
        <div class="card p-5">
            <h4 class="font-bold text-slate-700 mb-3 flex items-center gap-2">
                <i class="fas fa-history text-slate-400"></i> History & Notes
            </h4>
            <div class="bg-slate-50 rounded-lg p-3 text-sm text-slate-700 whitespace-pre-wrap font-mono text-xs">{{ $employee->bio }}</div>
        </div>
        @endif

        {{-- If confirmed --}}
        @if(in_array($employee->probation_status, ['passed', 'failed']))
        <div class="card p-5 bg-{{ $employee->probation_status === 'passed' ? 'green' : 'red' }}-50 border-{{ $employee->probation_status === 'passed' ? 'green' : 'red' }}-200">
            <div class="flex items-center gap-3">
                <i class="fas fa-{{ $employee->probation_status === 'passed' ? 'check-circle text-green' : 'times-circle text-red' }}-500 text-3xl"></i>
                <div>
                    <h4 class="font-bold text-{{ $employee->probation_status === 'passed' ? 'green' : 'red' }}-800">
                        Probation {{ ucfirst($employee->probation_status) }}
                    </h4>
                    <p class="text-sm text-{{ $employee->probation_status === 'passed' ? 'green' : 'red' }}-600">
                        Confirmed on {{ $employee->probation_confirmed_at?->format('d M Y') }}
                        @if($employee->probationConfirmedBy) by {{ $employee->probationConfirmedBy->name }} @endif
                    </p>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<script>
document.querySelectorAll('.outcome-radio').forEach(function(r) {
    r.addEventListener('change', function() {
        document.querySelectorAll('.outcome-btn').forEach(function(b) {
            b.classList.remove('border-green-400','border-blue-400','border-red-400','bg-green-50','bg-blue-50','bg-red-50');
            b.classList.add('border-slate-200');
        });
        const btn = this.nextElementSibling;
        const colors = {passed:'green', extended:'blue', failed:'red'};
        const c = colors[this.value] || 'slate';
        btn.classList.remove('border-slate-200');
        btn.classList.add('border-' + c + '-400', 'bg-' + c + '-50');
        document.getElementById('confirmBtn').disabled = false;
        document.getElementById('extendedDateField').classList.toggle('hidden', this.value !== 'extended');
    });
});
</script>
@endsection
