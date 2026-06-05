@extends("layouts.app")
@section("title","Assign Salary")
@section("content")
<x-page-header title="Assign Salary"><a href="{{ route('salary.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></x-page-header>
<div class="card p-6 max-w-2xl">
    <form method="POST" action="{{ route('salary.store') }}" class="space-y-4" x-data="{ salaryType: 'monthly' }">@csrf
        <div>
            <label class="form-label">Employee *</label>
            <select name="employee_id" class="form-select select2-ajax-employees" required></select>
        </div>

        {{-- Salary Type --}}
        <div>
            <label class="form-label">Salary Type *</label>
            <select name="salary_type" class="form-input" x-model="salaryType" required>
                <option value="monthly">Monthly (Fixed monthly salary with pro-rata)</option>
                <option value="daily">Daily Rate (Pay = Daily Rate × Days Worked)</option>
                <option value="hourly">Hourly Rate (Pay = Hourly Rate × Hours Worked)</option>
            </select>
        </div>

        {{-- Basic Salary / Rate Amount --}}
        <div>
            <label class="form-label">
                <span x-show="salaryType === 'monthly'">Monthly Salary (UGX) *</span>
                <span x-show="salaryType === 'daily'" x-cloak>Daily Rate (UGX per day) *</span>
                <span x-show="salaryType === 'hourly'" x-cloak>Hourly Rate (UGX per hour) *</span>
            </label>
            <input type="number" name="basic_salary" class="form-input" step="0.01" min="0" required>
            <p class="text-xs text-slate-400 mt-1">
                <span x-show="salaryType === 'monthly'">Employee receives this amount per month. Pro-rata applied for partial months.</span>
                <span x-show="salaryType === 'daily'" x-cloak>Employee is paid per day worked. E.g. UGX 10,000/day × 26 days = UGX 260,000.</span>
                <span x-show="salaryType === 'hourly'" x-cloak>Employee is paid per hour worked. Regular hours = days worked × 8 hrs.</span>
            </p>
        </div>

        <div>
            <label class="form-label">Effective From *</label>
            <input type="date" name="effective_from" value="{{ date('Y-m-d') }}" class="form-input" required>
        </div>

        <div>
            <label class="form-label mb-2">Salary Components</label>
            <div class="space-y-2">
                @foreach($components as $comp)
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <input type="checkbox" name="components[{{ $comp->id }}][component_id]" value="{{ $comp->id }}" id="comp_{{ $comp->id }}" class="rounded">
                    <label for="comp_{{ $comp->id }}" class="text-sm font-medium text-slate-700 flex-1">
                        {{ $comp->name }}
                        <span class="badge-{{ $comp->type === 'allowance' ? 'green' : 'red' }} ml-1">{{ ucfirst($comp->type) }}</span>
                    </label>
                    <span class="text-sm text-slate-500">{{ $comp->is_fixed ? 'UGX '.number_format($comp->amount, 2) : $comp->percentage.'%' }}</span>
                </div>
                @endforeach
            </div>
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Assign Salary</button>
    </form>
</div>
@endsection
