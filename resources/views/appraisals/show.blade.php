@extends('layouts.app')
@section('title', 'Individual Balanced Score Card')
@section('content')

@php
    $emp   = $appraisal->employee;
    $bands = \App\Models\Appraisal::BANDS;
    $isAdmin = auth()->user()->hasAnyRole(['super-admin', 'hr-admin']);
    $scoredCount = $appraisal->kpis->whereNotNull('rating')->count();
@endphp

<x-page-header title="Individual Balanced Score Card" subtitle="{{ $appraisal->title }}">
    <a href="{{ route('appraisals.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
    <span class="badge-{{ $appraisal->statusBadge() }}">{{ $appraisal->statusLabel() }}</span>
</x-page-header>

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

@if($isAdmin)
{{-- An administrator is not at any one step of this card, so the things only
     they can do are gathered here rather than scattered through the flow. --}}
<div class="card p-5 mb-5 border-l-4 border-blue-500">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h3 class="font-semibold text-slate-800">Administrator controls</h3>
            <p class="text-xs text-slate-500 mt-1">
                {{ $appraisal->kpis->count() }} KPI(s) on this card, weights totalling
                <strong class="{{ $appraisal->weightIsComplete() ? 'text-green-700' : 'text-amber-700' }}">{{ round($appraisal->totalWeight(), 2) }}%</strong>.
                @if(! $appraisal->weightIsComplete())
                    They must total exactly 100% before this can be sent.
                @endif
            </p>
        </div>

        <form method="POST" action="{{ route('appraisals.destroy', $appraisal) }}"
              onsubmit="return confirm('Delete this appraisal for good? This cannot be undone.')">
            @csrf @method('DELETE')
            <button class="btn-secondary text-rose-600 text-sm">
                <i class="fas fa-trash mr-1"></i> Delete appraisal
            </button>
        </form>
    </div>

    {{-- Adds what is missing rather than replacing what somebody has tuned:
         a KRA already on the card is skipped, never duplicated. --}}
    @if(isset($templates) && $templates->isNotEmpty())
    <form method="POST" action="{{ route('appraisals.import-kpis', $appraisal) }}"
          class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
        @csrf
        <div class="flex-1 min-w-[16rem]">
            <label class="form-label">Import KPIs from a template</label>
            <select name="appraisal_template_id" class="form-select" required>
                <option value="">Choose a template…</option>
                @foreach($templates as $t)
                <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->kpis_count ?? $t->kpis->count() }} KPIs)</option>
                @endforeach
            </select>
        </div>
        <button class="btn-primary"><i class="fas fa-file-import mr-1"></i> Import</button>
        <p class="basis-full text-xs text-slate-500">
            KRAs already on this card are skipped, not duplicated.
        </p>
    </form>
    @endif
</div>
@endif

{{-- ── Header block: employee | immediate manager ── --}}
<div class="card p-0 overflow-hidden mb-5">
    <div class="bg-slate-800 text-white text-center py-2.5 font-bold tracking-wide text-sm">
        INDIVIDUAL BALANCED SCORE CARD
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 divide-x divide-slate-100">
        <div class="p-4 space-y-2 text-sm">
            <div class="flex"><span class="w-40 font-semibold text-slate-600">Employee's Name:</span>
                <span class="text-slate-800">{{ $emp?->full_name ?? '—' }}</span></div>
            <div class="flex"><span class="w-40 font-semibold text-slate-600">Job Title:</span>
                <span class="text-slate-800">{{ $emp?->designation?->title ?? '—' }}</span></div>
            <div class="flex"><span class="w-40 font-semibold text-slate-600">Employee No.</span>
                <span class="text-slate-800">{{ $emp?->emp_number ?? '—' }}</span></div>
        </div>
        <div class="p-4 space-y-2 text-sm">
            <div class="flex"><span class="w-40 font-semibold text-slate-600">Immediate Manager:</span>
                <span class="text-slate-800">{{ $appraisal->initiator?->name ?? '—' }}</span></div>
            <div class="flex"><span class="w-40 font-semibold text-slate-600">Appraised by:</span>
                <span class="text-slate-800">{{ $appraisal->appraiser?->name ?? 'Not sent yet' }}</span></div>
            <div class="flex"><span class="w-40 font-semibold text-slate-600">For Review Period:</span>
                <span class="text-slate-800">
                    {{ $appraisal->review_from?->format('d M Y') ?? '—' }} –
                    {{ $appraisal->review_to?->format('d M Y') ?? '—' }}
                </span></div>
        </div>
    </div>
    <div class="px-4 py-2 bg-slate-50 border-t border-slate-100 flex flex-wrap gap-4 text-xs text-slate-500">
        <span><strong class="text-slate-700">Type:</strong>
            {{ $appraisal->type === 'external' ? 'External — appraised by client' : 'Internal' }}</span>
        @if($appraisal->client)<span><strong class="text-slate-700">Client:</strong> {{ $appraisal->client->company_name }}</span>@endif
        <span><strong class="text-slate-700">Period:</strong> {{ $appraisal->period ?? '—' }} {{ $appraisal->year }}</span>
    </div>
</div>

{{-- ── The scorecard ── --}}
{{-- One table, two authors. The appraiser scores here; before them, the employee
     fills the same rows in with what they actually achieved. The form posts to
     whichever step the card is at. --}}
<form method="POST" action="{{ $canSelfAssess
        ? route('appraisals.self-assessment.save', $appraisal)
        : route('appraisals.score', $appraisal) }}">
@csrf
<div class="card overflow-x-auto mb-5">
    <table class="w-full text-sm" style="min-width:1100px">
        <thead>
            <tr class="bg-slate-100 text-slate-700">
                <th class="px-3 py-2 text-left w-64">Key Result Area</th>
                <th class="px-3 py-2 text-left w-72">Performance Measures</th>
                <th class="px-3 py-2 text-center w-24">Target</th>
                <th class="px-3 py-2 text-center w-28">Actual Achieved</th>
                <th class="px-3 py-2 text-center w-28">% Target Achieved</th>
                {{-- Shown from the moment the employee submits, so the appraiser
                     scores with their account in view rather than after it. --}}
                <th class="px-3 py-2 text-center w-32">Self-rating</th>
                <th class="px-3 py-2 text-center w-40">Rating (1–5)</th>
                <th class="px-3 py-2 text-center w-24">Weighting</th>
                <th class="px-3 py-2 text-center w-28">Weighted Index</th>
                <th class="px-3 py-2 text-left w-56">Evidence / Comments</th>
            </tr>
        </thead>
        <tbody>
        @foreach($grouped as $key => $group)
            <tr class="bg-yellow-200">
                <td colspan="10" class="px-3 py-2 font-bold text-slate-900">
                    {{ $loop->iteration }}. {{ strtoupper($group['label']) }} — {{ rtrim(rtrim(number_format($group['weight'], 2), '0'), '.') }}%
                </td>
            </tr>
            @forelse($group['rows'] as $kpi)
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="px-3 py-2 align-top text-slate-800">{{ $kpi->kra_name }}</td>
                <td class="px-3 py-2 align-top text-slate-600 text-xs">{{ $kpi->performance_measure }}</td>
                <td class="px-3 py-2 align-top text-center text-slate-700">
                    {{-- The target was display-only, so a KPI that arrived from a
                         template without one could never be given a target at all -
                         and "% Target Achieved" is derived from it, so that column
                         stayed empty too. The scorer sets it; the employee does
                         not, because a target is set for you, not by you. --}}
                    @if($canScore)
                        <input type="text" name="kpi[{{ $kpi->id }}][target]"
                               value="{{ $kpi->target }}" class="form-input text-center py-1 text-sm"
                               placeholder="e.g. 30">
                    @else
                        {{ $kpi->target ?: '—' }}
                    @endif
                </td>
                <td class="px-3 py-2 align-top text-center">
                    {{-- Written by the employee first and editable by the appraiser
                         afterwards: it is a fact about the period, not an opinion,
                         so it is corrected rather than duplicated. --}}
                    @if($canScore || $canSelfAssess)
                        <input type="text" name="kpi[{{ $kpi->id }}][actual_achieved]"
                               value="{{ $kpi->actual_achieved }}" class="form-input text-center py-1 text-sm">
                    @else
                        {{ $kpi->actual_achieved ?? '—' }}
                    @endif
                </td>

                <td class="px-3 py-2 align-top text-center">
                    {{-- The employee normally writes this at their own step. An
                         administrator can record it as well - often they are
                         entering a card that was filled in on paper - but an
                         ordinary appraiser cannot, because writing somebody
                         else's self-rating is not scoring, it is inventing. --}}
                    @if($canSelfAssess || $isAdmin)
                        <div class="flex justify-center gap-1">
                            @for($r = 1; $r <= 5; $r++)
                                <label class="cursor-pointer">
                                    <input type="radio" class="sr-only peer"
                                           name="kpi[{{ $kpi->id }}][self_rating]" value="{{ $r }}"
                                           @checked((int) $kpi->self_rating === $r)>
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded border
                                                 border-slate-300 text-xs text-slate-600
                                                 peer-checked:bg-blue-600 peer-checked:text-white
                                                 peer-checked:border-blue-600">{{ $r }}</span>
                                </label>
                            @endfor
                        </div>
                    @elseif($kpi->self_rating)
                        {{-- Flagged when the two disagree by more than one point.
                             That gap is the conversation the appraisal exists to
                             have, so it should be visible rather than buried. --}}
                        @php($gap = $kpi->rating ? abs((int) $kpi->self_rating - (int) $kpi->rating) : 0)
                        <span class="inline-flex items-center gap-1 {{ $gap > 1 ? 'text-amber-600 font-semibold' : 'text-slate-600' }}">
                            {{ $kpi->self_rating }}
                            @if($gap > 1)
                                <i class="fas fa-triangle-exclamation text-xs" title="Differs from the appraiser by {{ $gap }} points"></i>
                            @endif
                        </span>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </td>
                <td class="px-3 py-2 align-top text-center text-slate-700">
                    {{ $kpi->target_percent !== null ? number_format($kpi->target_percent, 0) . '%' : '—' }}
                </td>
                <td class="px-3 py-2 align-top">
                    @if($canScore)
                        {{-- 1–5 across, exactly like the five rating columns in the sheet --}}
                        <div class="flex justify-center gap-1">
                            @for($r = 1; $r <= 5; $r++)
                            <label class="cursor-pointer">
                                <input type="radio" name="kpi[{{ $kpi->id }}][rating]" value="{{ $r }}"
                                       class="sr-only peer" {{ (int) $kpi->rating === $r ? 'checked' : '' }}>
                                <span class="inline-flex w-7 h-7 items-center justify-center rounded border border-slate-300
                                             text-xs font-semibold text-slate-600
                                             peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">
                                    {{ $r }}
                                </span>
                            </label>
                            @endfor
                        </div>
                    @else
                        <div class="flex justify-center gap-1">
                            @for($r = 1; $r <= 5; $r++)
                            <span class="inline-flex w-7 h-7 items-center justify-center rounded border text-xs font-semibold
                                {{ (int) $kpi->rating === $r ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 text-slate-300' }}">
                                {{ (int) $kpi->rating === $r ? $r : '' }}
                            </span>
                            @endfor
                        </div>
                    @endif
                </td>
                <td class="px-3 py-2 align-top text-center font-medium text-slate-700">
                    {{ rtrim(rtrim(number_format($kpi->weightage, 2), '0'), '.') }}%
                </td>
                <td class="px-3 py-2 align-top text-center font-semibold text-slate-800">
                    {{ $kpi->weighted_index !== null ? number_format($kpi->weighted_index, 2) : '—' }}
                </td>
                <td class="px-3 py-2 align-top text-xs text-slate-500">
                    @if($canScore)
                        <input type="text" name="kpi[{{ $kpi->id }}][evidence_note]"
                               value="{{ $kpi->evidence_note }}" class="form-input py-1 text-xs"
                               placeholder="e.g. Survey forms">
                    @else
                        {{ $kpi->evidence_note }}
                    @endif
                    @foreach($kpi->attachments as $att)
                        <a href="{{ route('appraisals.attachments.download', [$appraisal, $att]) }}"
                           class="block text-blue-600 hover:underline mt-1">
                            <i class="fas fa-paperclip mr-1"></i>{{ $att->original_name }}
                        </a>
                    @endforeach
                </td>
            </tr>
            @empty
            <tr><td colspan="10" class="px-3 py-3 text-center text-xs text-slate-400">No KPIs under this perspective.</td></tr>
            @endforelse
            <tr class="bg-slate-100 font-semibold text-slate-800">
                <td class="px-3 py-2" colspan="7">Total Rating</td>
                <td class="px-3 py-2 text-center text-red-600">{{ rtrim(rtrim(number_format($group['weight'], 2), '0'), '.') }}%</td>
                <td class="px-3 py-2 text-center">{{ number_format($group['index'], 2) }}</td>
                <td></td>
            </tr>
        @endforeach

            {{-- Overall --}}
            <tr class="bg-green-200 font-bold text-slate-900">
                <td class="px-3 py-3" colspan="6">OVERALL PERFORMANCE RATING</td>
                <td class="px-3 py-3 text-right">OVERALL RATING</td>
                <td class="px-3 py-3 text-center">{{ rtrim(rtrim(number_format($appraisal->totalWeight(), 2), '0'), '.') }}%</td>
                <td class="px-3 py-3 text-center text-lg">{{ number_format($appraisal->overall_index ?? 0, 2) }}</td>
                <td class="px-3 py-3 text-center text-lg">{{ number_format($appraisal->overall_percent ?? 0, 0) }}%</td>
            </tr>
        </tbody>
    </table>

    @if($canSelfAssess)
        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
            <button class="btn-secondary text-sm">
                <i class="fas fa-floppy-disk mr-1"></i> Save progress
            </button>

            {{-- A separate form so saving and submitting cannot be confused. The
                 card is long; people come back to it. --}}
            <span class="text-xs text-slate-500">
                Save as often as you like — nothing leaves your hands until you submit.
            </span>
        </div>
    @endif
</div>

@if($canScore)
<div class="flex flex-wrap gap-3 mb-6">
    <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Scores</button>
    <p class="text-xs text-slate-500 self-center">
        Saving recalculates the weighted index and overall rating. Return it when every KPI is rated.
    </p>
</div>
@endif
</form>

@if($canSelfAssess)
    <div class="card p-5 mb-5 border-l-4 border-blue-500">
        <h3 class="font-semibold text-slate-800">Finished?</h3>
        <p class="text-sm text-slate-600 mt-1">
            Submitting sends this to
            <strong>{{ $appraisal->appraiser?->name ?? 'your appraiser' }}</strong> for scoring.
            You will see it again at the end to read the final ratings and sign it off.
        </p>

        @php($unrated = $appraisal->kpis->whereNull('self_rating')->count())

        @if($unrated)
            {{-- Said before they press, rather than as an error afterwards. --}}
            <p class="text-sm text-amber-700 mt-3">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                {{ $unrated }} of {{ $appraisal->kpis->count() }} still need a self-rating.
            </p>
        @endif

        <form method="POST" action="{{ route('appraisals.self-assessment.submit', $appraisal) }}"
              class="mt-4"
              onsubmit="return confirm('Send this to your appraiser? You will not be able to change it afterwards.')">
            @csrf
            <button class="btn-primary" @disabled($unrated > 0)>
                <i class="fas fa-paper-plane mr-1"></i> Submit for scoring
            </button>
        </form>
    </div>
@endif

{{-- ── Rating scale + overall band ── --}}
<div class="card p-4 mb-5">
    <p class="font-semibold text-slate-700 text-sm mb-3">
        Please rate the staff member's overall performance
    </p>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
        @foreach($bands as $n => $band)
        <div class="p-3 rounded-lg border text-center
            {{ $appraisal->overall_band === $n ? 'bg-emerald-50 border-emerald-400' : 'bg-slate-50 border-slate-200' }}">
            <p class="font-bold text-sm {{ $appraisal->overall_band === $n ? 'text-emerald-700' : 'text-slate-600' }}">
                {{ $n }} = {{ $band['label'] }}
            </p>
            <p class="text-xs text-slate-400">{{ $band['range'] }}</p>
            @if($appraisal->overall_band === $n)
                <p class="mt-1 text-xs font-bold text-emerald-700 uppercase">{{ $band['label'] }}</p>
            @endif
        </div>
        @endforeach
    </div>
</div>

{{-- ── Part II ── --}}
<div class="card p-5 mb-5">
    <h3 class="font-semibold text-slate-800 mb-1">Part II — Mitigating Factors / Areas of Development</h3>
    <p class="text-xs text-slate-500 mb-4">Problem areas, what was agreed about them, and by when.</p>

    @forelse($appraisal->actions as $action)
    <div class="p-3 mb-2 bg-slate-50 border border-slate-200 rounded-lg">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div><p class="text-xs font-semibold text-slate-500 uppercase">Problem Area</p>
                <p class="text-slate-800">{{ $action->problem_area }}</p></div>
            <div><p class="text-xs font-semibold text-slate-500 uppercase">Agreed Remedial Action</p>
                <p class="text-slate-800">{{ $action->remedial_action ?? '—' }}</p></div>
            <div class="flex items-start justify-between">
                <div><p class="text-xs font-semibold text-slate-500 uppercase">By When</p>
                    <p class="text-slate-800">{{ $action->by_when?->format('d M Y') ?? '—' }}</p></div>
                @if($appraisal->status !== 'completed')
                <form method="POST" action="{{ route('appraisals.actions.destroy', [$appraisal, $action]) }}"
                      onsubmit="return confirm('Remove this development area?')">
                    @csrf @method('DELETE')
                    <button class="text-rose-500 hover:text-rose-700 text-xs"><i class="fas fa-trash"></i></button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @empty
    <p class="text-sm text-slate-400 mb-3">Nothing recorded yet.</p>
    @endforelse

    @if($appraisal->status !== 'completed')
    <form method="POST" action="{{ route('appraisals.actions.store', $appraisal) }}"
          class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3 pt-3 border-t border-slate-100">
        @csrf
        <input type="text" name="problem_area" class="form-input md:col-span-1" placeholder="Problem area" required>
        <input type="text" name="remedial_action" class="form-input md:col-span-2" placeholder="Agreed remedial action">
        <div class="flex gap-2">
            <input type="date" name="by_when" class="form-input">
            <button class="btn-secondary whitespace-nowrap"><i class="fas fa-plus"></i></button>
        </div>
    </form>
    @endif
</div>

{{-- ── Evidence ── --}}
<div class="card p-5 mb-5">
    <h3 class="font-semibold text-slate-800 mb-1">Evidence</h3>
    <p class="text-xs text-slate-500 mb-4">
        Customer surveys, minutes, reports — anything backing the scores above.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-4">
        @forelse($appraisal->attachments as $att)
        <div class="flex items-center gap-3 p-2.5 bg-slate-50 border border-slate-200 rounded-lg">
            <i class="fas {{ $att->isImage() ? 'fa-image' : 'fa-file-lines' }} text-slate-400"></i>
            <div class="flex-1 min-w-0">
                <a href="{{ route('appraisals.attachments.download', [$appraisal, $att]) }}"
                   class="text-sm text-blue-600 hover:underline block truncate">
                    {{ $att->label ?: $att->original_name }}
                </a>
                <p class="text-xs text-slate-400">
                    {{ $att->humanSize() }} · {{ $att->uploader?->name }} ·
                    {{ $att->kpi ? $att->kpi->kra_name : 'whole appraisal' }}
                </p>
            </div>
            @if($att->uploaded_by === auth()->id() || auth()->user()->hasAnyRole(['super-admin','hr-admin']))
            <form method="POST" action="{{ route('appraisals.attachments.destroy', [$appraisal, $att]) }}"
                  onsubmit="return confirm('Remove this evidence?')">
                @csrf @method('DELETE')
                <button class="text-rose-500 hover:text-rose-700 text-xs"><i class="fas fa-trash"></i></button>
            </form>
            @endif
        </div>
        @empty
        <p class="text-sm text-slate-400">No evidence attached yet.</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('appraisals.attachments.store', $appraisal) }}"
          enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-3 border-t border-slate-100">
        @csrf
        <input type="file" name="file" class="form-input md:col-span-1" required>
        <input type="text" name="label" class="form-input" placeholder="Label, e.g. Q1 customer survey">
        <select name="appraisal_kpi_id" class="form-select">
            <option value="">Whole appraisal</option>
            @foreach($appraisal->kpis as $kpi)
                <option value="{{ $kpi->id }}">{{ Str::limit($kpi->kra_name, 40) }}</option>
            @endforeach
        </select>
        <button class="btn-secondary"><i class="fas fa-upload mr-1"></i> Attach</button>
    </form>
</div>

{{-- ── Comments ── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-2">Employee's Comments</h3>
        @if($canSelf)
        <form method="POST" action="{{ route('appraisals.self', $appraisal) }}">
            @csrf
            <textarea name="employee_comment" rows="5" class="form-input" required
                      placeholder="Your own view of this review period…">{{ $appraisal->employee_comment }}</textarea>
            <button class="btn-primary mt-3"><i class="fas fa-signature mr-1"></i> Submit &amp; Sign Off</button>
        </form>
        @elseif($isAdmin)
        {{-- The employee writes this at their own step. An administrator
             correcting it afterwards is logged into the history. --}}
        <form method="POST" action="{{ route('appraisals.comments', $appraisal) }}">
            @csrf
            <textarea name="employee_comment" rows="5" class="form-input"
                      placeholder="The employee's own view of this review period…">{{ $appraisal->employee_comment }}</textarea>
            <button class="btn-secondary mt-3 text-sm"><i class="fas fa-pen mr-1"></i> Save comment</button>
        </form>
            @if($appraisal->employee_signed_at)
                <p class="text-xs text-slate-400 mt-3">
                    Signed by {{ $emp?->full_name }} · {{ $appraisal->employee_signed_at->format('d M Y H:i') }}
                </p>
            @endif
        @else
            <p class="text-sm text-slate-600 whitespace-pre-line">{{ $appraisal->employee_comment ?: '—' }}</p>
            @if($appraisal->employee_signed_at)
                <p class="text-xs text-slate-400 mt-3">
                    Signed by {{ $emp?->full_name }} · {{ $appraisal->employee_signed_at->format('d M Y H:i') }}
                </p>
            @endif
        @endif
    </div>

    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-1">Manager's Comments</h3>
        <p class="text-xs text-slate-400 mb-2">On the staff member's overall performance</p>
        @if($canConfirm)
        <form method="POST" action="{{ route('appraisals.confirm', $appraisal) }}">
            @csrf
            <textarea name="manager_comment" rows="5" class="form-input"
                      placeholder="Your comment on the overall performance…">{{ $appraisal->manager_comment }}</textarea>
            <button class="btn-primary mt-3"><i class="fas fa-check mr-1"></i> Confirm &amp; Send to Employee</button>
        </form>
        @elseif($isAdmin)
        <form method="POST" action="{{ route('appraisals.comments', $appraisal) }}">
            @csrf
            <textarea name="manager_comment" rows="5" class="form-input"
                      placeholder="Comment on the overall performance…">{{ $appraisal->manager_comment }}</textarea>
            <button class="btn-secondary mt-3 text-sm"><i class="fas fa-pen mr-1"></i> Save comment</button>
        </form>
            @if($appraisal->manager_signed_at)
                <p class="text-xs text-slate-400 mt-3">
                    Confirmed by {{ $appraisal->returnTo?->name ?? $appraisal->initiator?->name }} ·
                    {{ $appraisal->manager_signed_at->format('d M Y H:i') }}
                </p>
            @endif
        @else
            <p class="text-sm text-slate-600 whitespace-pre-line">{{ $appraisal->manager_comment ?: '—' }}</p>
            @if($appraisal->manager_signed_at)
                <p class="text-xs text-slate-400 mt-3">
                    Confirmed by {{ $appraisal->returnTo?->name ?? $appraisal->initiator?->name }} ·
                    {{ $appraisal->manager_signed_at->format('d M Y H:i') }}
                </p>
            @endif
        @endif
    </div>
</div>

{{-- ── Workflow actions ── --}}
@if($canScore)
<div class="card p-5 mb-5">
    <h3 class="font-semibold text-slate-800 mb-1">Return this appraisal</h3>
    <p class="text-xs text-slate-500 mb-3">
        It goes back to whoever started it by default, but you can send it to someone else —
        HR or the MD, for instance.
    </p>
    <form method="POST" action="{{ route('appraisals.return', $appraisal) }}"
          class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @csrf
        <select name="return_to_id" class="form-select select2" required>
            @foreach($returnees as $u)
                <option value="{{ $u->id }}" {{ $appraisal->initiated_by === $u->id ? 'selected' : '' }}>
                    {{ $u->name }}{{ $appraisal->initiated_by === $u->id ? ' — started this appraisal' : '' }}
                </option>
            @endforeach
        </select>
        <input type="text" name="comment" class="form-input" placeholder="Note (optional)">
        <button class="btn-primary"><i class="fas fa-paper-plane mr-1"></i> Return Appraisal</button>
    </form>
</div>
@endif

@if($canConfirm)
<div class="card p-5 mb-5">
    <h3 class="font-semibold text-slate-800 mb-3">Not satisfied?</h3>
    <form method="POST" action="{{ route('appraisals.send-back', $appraisal) }}"
          class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @csrf
        <input type="text" name="comment" class="form-input md:col-span-2" required
               placeholder="What needs revisiting?">
        <button class="inline-flex items-center justify-center gap-1 px-3 py-2 rounded-lg text-sm font-semibold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100">
            <i class="fas fa-undo"></i> Send Back to Appraiser
        </button>
    </form>
</div>
@endif

{{-- ── Trail ── --}}
<div class="card p-5">
    <h3 class="font-semibold text-slate-800 mb-3">History</h3>
    <div class="space-y-2">
        @forelse($appraisal->history as $h)
        <div class="flex items-start gap-3 text-sm">
            <span class="w-2 h-2 rounded-full bg-blue-400 mt-1.5 shrink-0"></span>
            <div>
                <p class="text-slate-700">
                    <strong>{{ $h->user?->name ?? 'System' }}</strong>
                    {{ str_replace('_', ' ', $h->action) }}
                    @if($h->to_status)<span class="text-slate-400">→ {{ $h->to_status }}</span>@endif
                </p>
                @if($h->comment)<p class="text-xs text-slate-500">{{ $h->comment }}</p>@endif
                <p class="text-xs text-slate-400">{{ $h->created_at->format('d M Y H:i') }}</p>
            </div>
        </div>
        @empty
        <p class="text-sm text-slate-400">Nothing yet.</p>
        @endforelse
    </div>
</div>
@endsection
