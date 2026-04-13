@extends("layouts.app")
@section("title","Leave Types")
@section("content")
<x-page-header title="Leave Types">
    <a href="{{ route('leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card overflow-hidden">
        <table class="w-full"><thead class="bg-slate-50"><tr>
            <th class="table-head px-6 py-3 text-left">Type</th>
            <th class="table-head px-4 py-3 text-center">Days/Year</th>
            <th class="table-head px-4 py-3 text-center">Paid</th>
            <th class="table-head px-4 py-3 text-center">Carry Forward</th>
            <th class="table-head px-4 py-3 text-left">Status</th>
        </tr></thead><tbody class="divide-y divide-slate-100">
        @foreach($types as $t)
        <tr class="table-row">
            <td class="px-6 py-3"><div class="flex items-center gap-3"><div class="w-3 h-3 rounded-full" style="background:{{ $t->color }}"></div><div><p class="text-sm font-medium text-slate-800">{{ $t->name }}</p><p class="text-xs text-slate-500 font-mono">{{ $t->code }}</p></div></div></td>
            <td class="px-4 py-3 text-center font-semibold text-slate-800">{{ $t->days_allowed }}</td>
            <td class="px-4 py-3 text-center"><span class="badge-{{ $t->is_paid?'green':'gray' }}">{{ $t->is_paid?'Yes':'No' }}</span></td>
            <td class="px-4 py-3 text-center"><span class="badge-{{ $t->carry_forward?'blue':'gray' }}">{{ $t->carry_forward?'Yes':'No' }}</span></td>
            <td class="px-4 py-3"><span class="badge-{{ $t->is_active?'green':'gray' }}">{{ $t->is_active?'Active':'Inactive' }}</span></td>
        </tr>
        @endforeach
        </tbody></table>
    </div>
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Add Leave Type</h3>
        <form method="POST" action="{{ route('leaves.types.store') }}" class="space-y-3">@csrf
            <div><label class="form-label">Name *</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">Code *</label><input type="text" name="code" class="form-input" placeholder="AL, SL..." required></div>
            <div><label class="form-label">Days Allowed *</label><input type="number" name="days_allowed" class="form-input" min="0" required></div>
            <div><label class="form-label">Color</label><input type="color" name="color" value="#3b82f6" class="form-input h-10"></div>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="is_paid" value="1" checked class="rounded"> Paid</label>
                <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="carry_forward" value="1" class="rounded"> Carry Forward</label>
            </div>
            <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-plus"></i> Create Type</button>
        </form>
    </div>
</div>
@endsection
