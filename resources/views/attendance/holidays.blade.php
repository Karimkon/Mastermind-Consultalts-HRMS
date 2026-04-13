@extends("layouts.app")
@section("title","Public Holidays")
@section("content")
<x-page-header title="Public Holidays">
    <a href="{{ route('attendance.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card overflow-hidden">
        <table class="w-full"><thead class="bg-slate-50"><tr>
            <th class="table-head px-6 py-3 text-left">Holiday</th>
            <th class="table-head px-4 py-3 text-left">Date</th>
            <th class="table-head px-4 py-3 text-left">Recurring</th>
            <th class="table-head px-4 py-3 text-left">Actions</th>
        </tr></thead><tbody class="divide-y divide-slate-100">
        @foreach($holidays as $h)
        <tr class="table-row">
            <td class="px-6 py-3 text-sm font-medium text-slate-800">{{ $h->name }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $h->date->format('M d, Y') }}</td>
            <td class="px-4 py-3"><span class="badge-{{ $h->is_recurring?'green':'gray' }}">{{ $h->is_recurring?'Yes':'No' }}</span></td>
            <td class="px-4 py-3"><form method="POST" action="{{ route('attendance.holidays.destroy',$h) }}" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-600 text-xs"><i class="fas fa-trash"></i></button></form></td>
        </tr>
        @endforeach
        </tbody></table>
    </div>
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Add Holiday</h3>
        <form method="POST" action="{{ route('attendance.holidays.store') }}" class="space-y-3">@csrf
            <div><label class="form-label">Name *</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">Date *</label><input type="date" name="date" class="form-input" required></div>
            <div><label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="is_recurring" value="1" class="rounded"> Recurring yearly</label></div>
            <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-plus"></i> Add Holiday</button>
        </form>
    </div>
</div>
@endsection
