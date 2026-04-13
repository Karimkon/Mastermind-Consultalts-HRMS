@extends("layouts.app")
@section("title","Salary Components")
@section("content")
<x-page-header title="Salary Components">
    <a href="{{ route('salary.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card overflow-hidden">
        <table class="w-full"><thead class="bg-slate-50"><tr>
            <th class="table-head px-6 py-3 text-left">Component</th>
            <th class="table-head px-4 py-3 text-left">Type</th>
            <th class="table-head px-4 py-3 text-left">Amount/Rate</th>
            <th class="table-head px-4 py-3 text-left">Taxable</th>
            <th class="table-head px-4 py-3 text-left">Status</th>
        </tr></thead><tbody class="divide-y divide-slate-100">
        @foreach($components as $comp)
        <tr class="table-row">
            <td class="px-6 py-3"><p class="text-sm font-medium text-slate-800">{{ $comp->name }}</p><p class="text-xs text-slate-400 font-mono">{{ $comp->code }}</p></td>
            <td class="px-4 py-3"><span class="badge-{{ $comp->type==='allowance'?'green':'red' }}">{{ ucfirst($comp->type) }}</span></td>
            <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $comp->is_fixed ? 'UGX '.number_format($comp->amount,2) : $comp->percentage.'%' }}</td>
            <td class="px-4 py-3"><span class="badge-{{ $comp->is_taxable?'yellow':'gray' }}">{{ $comp->is_taxable?'Yes':'No' }}</span></td>
            <td class="px-4 py-3"><span class="badge-{{ $comp->is_active?'green':'gray' }}">{{ $comp->is_active?'Active':'Inactive' }}</span></td>
        </tr>
        @endforeach
        </tbody></table>
    </div>
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Add Component</h3>
        <form method="POST" action="{{ route('salary.components.store') }}" class="space-y-3">@csrf
            <div><label class="form-label">Name *</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">Code *</label><input type="text" name="code" class="form-input" placeholder="HRA, PF..." required></div>
            <div><label class="form-label">Type</label><select name="type" class="form-select"><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select></div>
            <div><label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="is_fixed" value="1" checked class="rounded"> Fixed Amount</label></div>
            <div><label class="form-label">Amount (R)</label><input type="number" name="amount" class="form-input" step="0.01" min="0"></div>
            <div><label class="form-label">OR Percentage (%)</label><input type="number" name="percentage" class="form-input" step="0.01" min="0" max="100"></div>
            <div><label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="is_taxable" value="1" class="rounded"> Taxable</label></div>
            <button type="submit" class="btn-primary w-full justify-center">Add Component</button>
        </form>
    </div>
</div>
@endsection
