@php
    $measured = $session->participants->filter->hasMeasure();
    $avgGain  = $measured->count()
        ? round($measured->avg(fn($p) => $p->improvementPercent()), 1)
        : null;
@endphp

<div class="card p-4">
    <div class="flex items-start justify-between flex-wrap gap-3 mb-3">
        <div>
            <h2 class="font-semibold text-slate-800">
                Who attends
                <span class="text-slate-400 font-normal">({{ $session->participants->count() }})</span>
            </h2>
            <p class="text-xs text-slate-500">
                Attendance, result, and what the training actually changed.
            </p>
        </div>

        {{-- Return on training, once anybody has been measured. Cost is only half
             the question; this is the other half. --}}
        @if($avgGain !== null)
        <div class="px-3 py-2 rounded-lg border {{ $avgGain >= 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200' }}">
            <p class="text-[11px] uppercase tracking-wide {{ $avgGain >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                Average improvement
            </p>
            <p class="text-lg font-bold {{ $avgGain >= 0 ? 'text-emerald-800' : 'text-rose-800' }}">
                {{ $avgGain >= 0 ? '+' : '' }}{{ $avgGain }}%
            </p>
            <p class="text-[11px] text-slate-500">
                across {{ $measured->count() }} of {{ $session->participants->count() }} measured
                @if($session->costPerParticipant())
                    · {{ number_format($session->costPerParticipant(), 0) }} per person
                @endif
            </p>
        </div>
        @endif
    </div>

    @forelse($session->participants as $p)
    <div class="border border-slate-200 rounded-lg p-3 mb-2" x-data="{ open: false }">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <img src="{{ $p->employee->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-800">{{ $p->employee->full_name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $p->employee->department?->name ?? 'No department' }}
                        @if($p->recommender) · put forward by {{ $p->recommender->name }} @endif
                        @if($p->appraisal_id) · from an appraisal @endif
                    </p>
                    @if(filled($p->reason))
                        <p class="text-xs text-slate-600 mt-0.5">{{ $p->reason }}</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @php $tone = ['attended'=>'green','absent'=>'red','withdrawn'=>'slate','confirmed'=>'blue','nominated'=>'yellow'][$p->attendance] ?? 'slate'; @endphp
                <span class="badge-{{ $tone }}">{{ ucfirst($p->attendance) }}</span>

                @if($p->hasMeasure())
                    <span class="text-xs font-semibold {{ $p->regressed() ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ $p->improvementPercent() >= 0 ? '+' : '' }}{{ $p->improvementPercent() }}%
                    </span>
                @endif

                <button type="button" @click="open = !open"
                        class="text-xs text-blue-600 hover:underline">
                    <span x-text="open ? 'Close' : 'Record'"></span>
                </button>
            </div>
        </div>

        <div x-show="open" x-cloak class="mt-3 pt-3 border-t border-slate-100">
            <form method="POST" action="{{ route('training.plan.participants.update', [$session, $p]) }}">
                @csrf @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                    <div class="sm:col-span-3">
                        <label class="form-label text-xs">Attendance</label>
                        <select name="attendance" class="form-input text-sm">
                            @foreach(['nominated','confirmed','attended','absent','withdrawn'] as $a)
                                <option value="{{ $a }}" @selected($p->attendance === $a)>{{ ucfirst($a) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="form-label text-xs">Completed on</label>
                        <input type="date" name="completed_on" class="form-input text-sm"
                               value="{{ $p->completed_on?->format('Y-m-d') }}">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="form-label text-xs">Score (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="score"
                               class="form-input text-sm" value="{{ $p->score }}">
                    </div>
                </div>

                {{-- The measure. One thing the person does, timed or counted before
                     and after - "builds the weekly report" at 120 minutes and then
                     30. Without a before there is nothing to compare against, so
                     it is worth setting when they are nominated, not afterwards. --}}
                <p class="text-xs font-semibold text-slate-600 mt-3 mb-1">Return on this training</p>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                    <div class="sm:col-span-4">
                        <label class="form-label text-xs">What is measured</label>
                        <input type="text" name="roi_metric" class="form-input text-sm"
                               value="{{ $p->roi_metric }}" placeholder="e.g. Time to build the weekly report">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label text-xs">Unit</label>
                        <input type="text" name="roi_unit" class="form-input text-sm"
                               value="{{ $p->roi_unit }}" placeholder="minutes">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label text-xs">Before</label>
                        <input type="number" step="0.01" name="roi_before" class="form-input text-sm"
                               value="{{ $p->roi_before }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label text-xs">After</label>
                        <input type="number" step="0.01" name="roi_after" class="form-input text-sm"
                               value="{{ $p->roi_after }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label text-xs">Measured on</label>
                        <input type="date" name="roi_measured_on" class="form-input text-sm"
                               value="{{ $p->roi_measured_on?->format('Y-m-d') }}">
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 mt-2 text-xs text-slate-600">
                    <input type="hidden" name="roi_lower_is_better" value="0">
                    <input type="checkbox" name="roi_lower_is_better" value="1" class="rounded"
                           @checked($p->roi_lower_is_better)>
                    Lower is better <span class="text-slate-400">(tick for time or errors; untick for output)</span>
                </label>

                <input type="text" name="roi_note" class="form-input text-sm mt-2"
                       value="{{ $p->roi_note }}" placeholder="How it was measured (optional)">

                <div class="flex items-center gap-3 mt-3">
                    <button class="btn-primary text-xs" data-loading-label="Saving…">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                    <button type="submit" form="drop-{{ $p->id }}" class="text-xs text-red-600 hover:underline">
                        Remove from this session
                    </button>
                </div>
            </form>

            <form method="POST" id="drop-{{ $p->id }}" class="hidden"
                  action="{{ route('training.plan.participants.remove', [$session, $p]) }}"
                  onsubmit="return confirm('Remove {{ addslashes($p->employee->full_name) }} from this session?')">
                @csrf @method('DELETE')
            </form>
        </div>
    </div>
    @empty
        <p class="text-sm text-amber-600 mb-2">
            <i class="fas fa-circle-exclamation"></i>
            Nobody is on this session yet. It cannot be submitted until somebody is.
        </p>
    @endforelse

    {{-- Nominate --}}
    <form method="POST" action="{{ route('training.plan.participants.add', $session) }}"
          class="grid grid-cols-1 sm:grid-cols-12 gap-2 mt-3 pt-3 border-t border-slate-100">
        @csrf
        <select name="employee_id" class="form-input select2 text-sm sm:col-span-5" required>
            <option value="">Choose someone…</option>
            @foreach($employees as $e)
                <option value="{{ $e->id }}">{{ $e->full_name }} — {{ $e->department?->name ?? 'No department' }}</option>
            @endforeach
        </select>
        <input type="text" name="reason" class="form-input text-sm sm:col-span-5"
               placeholder="Why they need it (optional)">
        <button class="btn-secondary text-sm sm:col-span-2" data-loading-label="Adding…">
            <i class="fas fa-user-plus mr-1"></i> Nominate
        </button>
    </form>
</div>
