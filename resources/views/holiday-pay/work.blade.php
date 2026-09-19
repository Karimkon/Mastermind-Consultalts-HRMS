@extends('layouts.app')
@section('title','Who Worked — ' . $holiday->name)
@section('content')

<x-page-header title="Who worked {{ $holiday->name }}?"
    subtitle="{{ $holiday->date->format('l d F Y') }} · {{ $client->company_name }}">
    <a href="{{ route('holiday-pay.index', ['client_id' => $client->id, 'year' => $holiday->year]) }}"
       class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="mb-5 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
    <i class="fas fa-circle-info mr-1"></i>
    Tick everyone who actually came in on this day. Once the holiday is approved for payment they are paid
    <strong>double</strong> their daily rate for it. Monthly-salaried staff are not listed — their salary
    already covers the holiday.
</div>

<form method="POST" action="{{ route('holiday-pay.work.store', $holiday) }}">
@csrf
<input type="hidden" name="client_id" value="{{ $client->id }}">

<div class="card overflow-hidden">
    <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between">
        <p class="text-sm font-medium text-slate-700">{{ $employees->count() }} casual staff on this site</p>
        <label class="text-sm text-slate-600 cursor-pointer">
            <input type="checkbox" class="mr-1"
                   onclick="document.querySelectorAll('.hw-check').forEach(c => c.checked = this.checked)">
            Select all
        </label>
    </div>

    <table class="w-full">
        <thead class="bg-slate-50"><tr>
            <th class="table-head px-6 py-3 text-left w-12"></th>
            <th class="table-head px-4 py-3 text-left">Employee</th>
            <th class="table-head px-4 py-3 text-left">Emp No</th>
            <th class="table-head px-4 py-3 text-left">Department</th>
            <th class="table-head px-4 py-3 text-right">Daily Rate</th>
            <th class="table-head px-4 py-3 text-right">If Worked</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($employees as $emp)
            @php
                $rate = (float) ($emp->salary?->basic_salary ?? 0);
                $isDaily = ($emp->salary?->salary_type ?? '') === 'daily';
            @endphp
            <tr class="table-row">
                <td class="px-6 py-3">
                    <input type="checkbox" class="hw-check" name="employee_ids[]" value="{{ $emp->id }}"
                           {{ in_array($emp->id, $workedIds) ? 'checked' : '' }}>
                </td>
                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $emp->full_name }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ $emp->emp_number }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ $emp->department?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-right text-sm text-slate-600">
                    {{ $rate > 0 ? number_format($rate) : '—' }}
                    <span class="text-xs text-slate-400">/{{ $emp->salary?->salary_type ?? '—' }}</span>
                </td>
                <td class="px-4 py-3 text-right text-sm font-semibold text-emerald-700">
                    {{ $rate > 0 && $isDaily ? number_format($rate * 2) : '—' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="py-12 text-center">
                <i class="fas fa-users-slash text-3xl text-slate-300 mb-3 block"></i>
                <p class="text-slate-500 text-sm">No daily or hourly staff assigned to {{ $client->company_name }}.</p>
                <p class="text-slate-400 text-xs mt-1">Holiday pay only applies to casual rates — monthly staff are already covered.</p>
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($employees->isNotEmpty())
<div class="flex gap-3 mt-4">
    <button type="submit" class="btn-primary"><i class="fas fa-save mr-2"></i>Save Who Worked</button>
    <a href="{{ route('holiday-pay.index', ['client_id' => $client->id, 'year' => $holiday->year]) }}"
       class="btn-secondary">Cancel</a>
</div>
@endif
</form>
@endsection
