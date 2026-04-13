@extends("layouts.app")
@section("title","Salary Grades")
@section("content")
<x-page-header title="Salary Grades"><a href="{{ route('salary.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></x-page-header>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card overflow-hidden">
        <table class="w-full"><thead class="bg-slate-50"><tr>
            <th class="table-head px-6 py-3 text-left">Grade</th>
            <th class="table-head px-4 py-3 text-left">Label</th>
            <th class="table-head px-4 py-3 text-right">Min Salary</th>
            <th class="table-head px-4 py-3 text-right">Max Salary</th>
        </tr></thead><tbody class="divide-y divide-slate-100">
        @foreach($grades as $g)
        <tr class="table-row"><td class="px-6 py-3 font-mono font-bold text-blue-600">{{ $g->grade }}</td><td class="px-4 py-3 text-sm text-slate-700">{{ $g->label }}</td><td class="px-4 py-3 text-right text-sm text-slate-600">UGX {{ number_format($g->basic_min,2) }}</td><td class="px-4 py-3 text-right text-sm text-slate-600">UGX {{ number_format($g->basic_max,2) }}</td></tr>
        @endforeach
        </tbody></table>
    </div>
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Add Grade</h3>
        <form method="POST" action="{{ route('salary.grades.store') }}" class="space-y-3">@csrf
            <div><label class="form-label">Grade *</label><input type="text" name="grade" class="form-input" placeholder="G1, G2..." required></div>
            <div><label class="form-label">Label *</label><input type="text" name="label" class="form-input" required></div>
            <div><label class="form-label">Min Salary *</label><input type="number" name="basic_min" class="form-input" step="0.01" required></div>
            <div><label class="form-label">Max Salary *</label><input type="number" name="basic_max" class="form-input" step="0.01" required></div>
            <button type="submit" class="btn-primary w-full justify-center">Add Grade</button>
        </form>
    </div>
</div>
@endsection
