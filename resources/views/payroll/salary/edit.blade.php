@extends('layouts.app')
@section('title', 'Edit Salary')
@section('content')
<x-page-header title="Edit Salary">
    <a href="{{ route('salary.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

<div class="card p-6 max-w-2xl" x-data="{ salaryType: '{{ $salary->salary_type ?? 'monthly' }}' }">
    <form method="POST" action="{{ route('salary.update', $salary) }}" class="space-y-4">
        @csrf @method('PUT')

        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-sm">
            <span class="font-medium text-slate-700">Employee:</span>
            {{ $salary->employee->full_name ?? '—' }}
        </div>

        {{-- Salary Type --}}
        <div>
            <label class="form-label">Salary Type *</label>
            <select name="salary_type" class="form-input" x-model="salaryType" required>
                <option value="monthly" @selected(($salary->salary_type ?? 'monthly') === 'monthly')>Monthly (Fixed monthly salary with pro-rata)</option>
                <option value="daily"   @selected($salary->salary_type === 'daily')>Daily Rate (Pay = Daily Rate × Days Worked)</option>
                <option value="hourly"  @selected($salary->salary_type === 'hourly')>Hourly Rate (Pay = Hourly Rate × Hours Worked)</option>
            </select>
        </div>

        {{-- Rate Amount --}}
        <div>
            <label class="form-label">
                <span x-show="salaryType === 'monthly'">Monthly Salary (UGX)</span>
                <span x-show="salaryType === 'daily'" x-cloak>Daily Rate (UGX per day)</span>
                <span x-show="salaryType === 'hourly'" x-cloak>Hourly Rate (UGX per hour)</span>
            </label>
            <input type="number" name="basic_salary" value="{{ $salary->basic_salary }}" class="form-input" step="0.01" required>
            <p class="text-xs text-slate-400 mt-1">
                <span x-show="salaryType === 'monthly'">Employee receives this amount per month. Pro-rata applied for partial months.</span>
                <span x-show="salaryType === 'daily'" x-cloak>Employee is paid per day worked. E.g. UGX 10,000/day × 26 days = UGX 260,000.</span>
                <span x-show="salaryType === 'hourly'" x-cloak>Employee is paid per hour worked. Regular hours = days worked × 8 hrs.</span>
            </p>
        </div>

        <div>
            <label class="form-label">Effective From</label>
            <input type="date" name="effective_from" value="{{ $salary->effective_from->format('Y-m-d') }}" class="form-input" required>
        </div>

        {{-- Salary Components --}}
        @if(isset($components) && $components->count())
        <div>
            <label class="form-label mb-2">Salary Components</label>
            <div class="space-y-2">
                @foreach($components as $comp)
                @php $assigned = collect($salary->components ?? [])->firstWhere('component_id', $comp->id); @endphp
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <input type="checkbox" name="components[{{ $comp->id }}][component_id]" value="{{ $comp->id }}"
                           id="comp_{{ $comp->id }}" class="rounded" @if($assigned) checked @endif>
                    <label for="comp_{{ $comp->id }}" class="text-sm font-medium text-slate-700 flex-1">
                        {{ $comp->name }}
                        <span class="badge-{{ $comp->type === 'allowance' ? 'green' : 'red' }} ml-1">{{ ucfirst($comp->type) }}</span>
                    </label>
                    <span class="text-sm text-slate-500">{{ $comp->is_fixed ? 'UGX '.number_format($comp->amount, 2) : $comp->percentage.'%' }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update</button>
    </form>
</div>
@endsection
