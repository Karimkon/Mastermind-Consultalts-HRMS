@extends('layouts.app')
@section('title', 'Select employees for payroll')
@section('content')

@php
    $clientName = $run->client->company_name ?? 'All clients';
    $period = \Carbon\Carbon::create($run->year, $run->month, 1)->format('F Y');
    $selectedCount = $existing->filter(fn ($d) => (int) $d > 0)->count();
@endphp

<x-page-header title="Who is on this payroll?"
               subtitle="{{ $period }} · {{ $clientName }}">
    @if($run->client_id)
    <button type="button" onclick="toggleAddEmployee()" class="btn-secondary">
        <i class="fas fa-user-plus mr-1"></i> Add an employee
    </button>
    @endif
    <a href="{{ route('account-manager.payroll.show', $run) }}" class="btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to run
    </a>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

{{-- Adding somebody who was never loaded: a new hire, or one the spreadsheet
     missed. It writes the same records the bulk import writes, so there is one
     kind of employee in the system and not two — and it is their own form,
     outside the selection form, because a form cannot be nested in another. --}}
@if($run->client_id)
<div id="addEmployee" class="mb-6 rounded-xl bg-white border border-slate-200 p-5" @if(! $errors->hasAny(['first_name','last_name','rate','email','salary_type'])) hidden @endif>
    <h3 class="font-semibold text-slate-700 mb-1">Add an employee to {{ $clientName }}</h3>
    <p class="text-xs text-slate-400 mb-4">
        Saved into Employee Central and assigned to this client, so they are there
        for every future run as well as this one.
    </p>

    <form method="POST" action="{{ route('account-manager.payroll.employees.add', $run) }}" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="form-label">First name</label>
                <input name="first_name" required value="{{ old('first_name') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Last name</label>
                <input name="last_name" required value="{{ old('last_name') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Engagement</label>
                <select name="employment_type" class="form-select">
                    @foreach(['casual' => 'Casual', 'contract' => 'Contract', 'full_time' => 'Full time', 'part_time' => 'Part time'] as $k => $v)
                        <option value="{{ $k }}" @selected(old('employment_type', 'casual') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="form-label">Paid</label>
                <select name="salary_type" class="form-select">
                    @foreach(['daily' => 'Per day', 'monthly' => 'Per month', 'hourly' => 'Per hour'] as $k => $v)
                        <option value="{{ $k }}" @selected(old('salary_type', 'daily') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Rate (UGX)</label>
                <input name="rate" type="number" min="1" step="1" required value="{{ old('rate') }}" class="form-input">
                <p class="text-xs text-slate-400 mt-1">Without this, payroll calculates nothing for them.</p>
            </div>
            <div>
                <label class="form-label">Days worked this month</label>
                <input name="days_worked" type="number" min="0" max="{{ $daysInMonth }}"
                       value="{{ old('days_worked') }}" class="form-input" placeholder="Leave blank to add without paying yet">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="form-label">Started on</label>
                <input name="hire_date" type="date" value="{{ old('hire_date', today()->toDateString()) }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Phone (optional)</label>
                <input name="phone" value="{{ old('phone') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">National ID (optional)</label>
                <input name="national_id" value="{{ old('national_id') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Email (optional)</label>
                <input name="email" type="email" value="{{ old('email') }}" class="form-input">
                <p class="text-xs text-slate-400 mt-1">Gives them a login and a payslip by email.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <button type="button" onclick="toggleAddEmployee()" class="btn-secondary">Cancel</button>
            <button class="btn-primary"><i class="fas fa-user-plus mr-1"></i> Add employee</button>
        </div>
    </form>
</div>
@endif

@if(session('error'))<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

<div class="mb-4 rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-900">
    <i class="fas fa-circle-info mr-1"></i>
    Tick only the people who worked this month, and enter the days each of them
    worked. Anybody left unticked is not paid and gets no payslip &mdash; rather than a
    payslip of zero that HR still has to review.
</div>

<form method="POST" action="{{ route('account-manager.payroll.select.store', $run) }}" id="selectForm">
    @csrf

    <div class="rounded-xl bg-white border border-slate-200 overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 p-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <button type="button" onclick="selectAll(true)" class="btn-secondary text-xs">
                    <i class="fas fa-check-double mr-1"></i> Select all
                </button>
                <button type="button" onclick="selectAll(false)" class="btn-secondary text-xs">
                    <i class="fas fa-xmark mr-1"></i> Clear all
                </button>
            </div>

            <div class="flex-1 min-w-[200px]">
                <input type="search" id="search" placeholder="Search by name or number&hellip;"
                       class="form-input" oninput="filterRows(this.value)">
            </div>

            <div class="text-sm text-slate-500 whitespace-nowrap">
                <span id="chosenCount" class="font-semibold text-slate-800">{{ $selectedCount }}</span>
                of {{ $employees->count() }} selected
            </div>

            {{-- Autosave state. Worth showing rather than hiding: somebody part
                 way through 439 employees needs to know their work is safe
                 before they close the laptop. --}}
            <div id="saveState" class="text-xs whitespace-nowrap text-slate-400">
                @if($selectedCount) Saved @else Not started @endif
            </div>
        </div>

        {{-- Set one number across everybody ticked, for the common case where a
             whole crew worked the same days. --}}
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 bg-slate-50 border-b border-slate-100 text-sm">
            <span class="text-slate-500">Set days for everyone selected:</span>
            <input type="number" id="bulkDays" min="0" max="{{ $daysInMonth }}"
                   class="form-input w-24" placeholder="0">
            <button type="button" onclick="applyBulkDays()" class="btn-secondary text-xs">Apply</button>
            <span class="text-xs text-slate-400">Each person can still be changed individually below.</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-header">
                        <th class="w-10"></th>
                        <th>Employee</th>
                        <th>Placement</th>
                        <th class="w-32">Days worked</th>
                    </tr>
                </thead>
                <tbody id="rows">
                    @forelse($employees as $e)
                    {{-- Ticked only where days were actually entered. The spreadsheet
                         import writes a row for everybody, so 433 of these carry zero —
                         and a row of zero is not a selection, which is exactly what the
                         engine now decides too. Showing them ticked would promise a
                         payslip that will not be produced. --}}
                    @php($isSelected = (int) ($existing[$e->id] ?? 0) > 0)
                    <tr class="table-row" data-name="{{ strtolower($e->full_name . ' ' . $e->emp_number) }}">
                        <td class="px-4 py-3">
                            <input type="checkbox" name="include[]" value="{{ $e->id }}"
                                   class="row-check w-4 h-4 accent-blue-600"
                                   onchange="rowToggled(this)"
                                   @checked($isSelected)>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ $e->full_name }}</p>
                            <p class="text-xs text-slate-400">{{ $e->emp_number }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">
                            {{ $e->department->name ?? $e->designation->title ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" name="days[{{ $e->id }}]"
                                   value="{{ $existing[$e->id] ?? '' }}"
                                   min="0" max="{{ $daysInMonth }}" placeholder="0"
                                   class="form-input w-24 days-input"
                                   @disabled(! $isSelected)>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">
                        No employees are assigned to this client.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
        <p class="text-xs text-slate-400">
            Your work saves by itself as you go. Saving only records the selection &mdash;
            the run is processed from the run page.
        </p>
        <button class="btn-primary"><i class="fas fa-floppy-disk mr-1"></i> Save &amp; go back</button>
    </div>
</form>

@push('scripts')
<script>
// A days box is only meaningful for somebody who is actually on the run, so it
// follows the tick rather than sitting there inviting a number that would be
// ignored. Disabled inputs are not posted, which is also what we want.
function rowToggled(box) {
    const days = box.closest('tr').querySelector('.days-input');
    days.disabled = !box.checked;
    if (!box.checked) days.value = '';
    updateCount();
}

function selectAll(state) {
    // Only what is currently visible, so "select all" after a search means
    // "all of these", which is what somebody filtering actually expects.
    document.querySelectorAll('#rows tr').forEach(function (tr) {
        if (tr.style.display === 'none') return;
        const box = tr.querySelector('.row-check');
        if (!box) return;
        box.checked = state;
        rowToggled(box);
    });
}

function applyBulkDays() {
    const value = document.getElementById('bulkDays').value;
    if (value === '') return;
    document.querySelectorAll('#rows tr').forEach(function (tr) {
        if (tr.style.display === 'none') return;
        const box = tr.querySelector('.row-check');
        if (box && box.checked) tr.querySelector('.days-input').value = value;
    });
}

function filterRows(term) {
    const q = (term || '').toLowerCase().trim();
    document.querySelectorAll('#rows tr').forEach(function (tr) {
        if (!tr.dataset.name) return;
        tr.style.display = (!q || tr.dataset.name.includes(q)) ? '' : 'none';
    });
}

function updateCount() {
    document.getElementById('chosenCount').textContent =
        document.querySelectorAll('.row-check:checked').length;
}

function toggleAddEmployee() {
    const panel = document.getElementById('addEmployee');
    if (!panel) return;
    panel.hidden = !panel.hidden;
    if (!panel.hidden) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        panel.querySelector('[name="first_name"]')?.focus();
    }
}

// ===== Autosave =====
//
// 439 employees is an afternoon's work. Losing it to a closed laptop, a flat
// battery or a dropped connection is not acceptable, so the selection saves
// itself as it is made and the screen says so.
//
// Everything already posts to the same endpoint the Save button uses; it simply
// answers with a receipt instead of a redirect when asked for JSON. So autosave
// and manual save cannot drift apart — there is only one way in.

let saveTimer = null;
let saving = false;
let dirty = false;

function setSaveState(text, tone) {
    const el = document.getElementById('saveState');
    el.textContent = text;
    el.className = 'text-xs whitespace-nowrap ' + ({
        saved: 'text-emerald-600',
        saving: 'text-slate-400',
        error: 'text-red-600 font-semibold',
    }[tone] || 'text-slate-400');
}

async function save(isBeacon) {
    if (saving) { dirty = true; return; }
    saving = true;
    dirty = false;
    setSaveState('Saving…', 'saving');

    const form = document.getElementById('selectForm');
    const body = new FormData(form);

    try {
        const r = await fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body,
            keepalive: !!isBeacon,   // let it finish if the page is closing
        });

        if (!r.ok) throw new Error('status ' + r.status);
        const d = await r.json();
        setSaveState('Saved ' + d.saved_at, 'saved');
    } catch (e) {
        // Say so loudly. A silent autosave failure is worse than none at all,
        // because it buys false confidence.
        setSaveState('NOT SAVED — use Save & go back', 'error');
    } finally {
        saving = false;
        if (dirty) scheduleSave();
    }
}

function scheduleSave() {
    clearTimeout(saveTimer);
    setSaveState('Unsaved changes…', 'saving');
    saveTimer = setTimeout(() => save(false), 1500);
}

// Any change to the selection or the days schedules a save.
document.addEventListener('DOMContentLoaded', function () {
    updateCount();

    document.getElementById('rows').addEventListener('change', scheduleSave);
    document.getElementById('rows').addEventListener('input', function (e) {
        if (e.target.classList.contains('days-input')) scheduleSave();
    });

    // Closing the tab mid-edit still saves what is there.
    window.addEventListener('beforeunload', function (e) {
        if (saveTimer || dirty || saving) {
            save(true);
            e.preventDefault();
            e.returnValue = '';
        }
    });
});

// The bulk buttons change many rows at once without firing per-row events.
['selectAll', 'applyBulkDays'].forEach(function (name) {
    const original = window[name];
    window[name] = function (...args) { original.apply(this, args); scheduleSave(); };
});
</script>
@endpush

@endsection
