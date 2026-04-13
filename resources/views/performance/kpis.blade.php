@extends('layouts.app')
@section('title', 'KPI Library')
@section('content')
<x-page-header title="KPI Library" subtitle="Key Performance Indicators">
    <button @click="$dispatch('open-modal-create-kpi')" class="btn-primary"><i class="fas fa-plus mr-1"></i> Add KPI</button>
</x-page-header>

<x-data-table>
    <thead><tr class="table-header"><th>KPI Name</th><th>Category</th><th>Weight</th><th>Description</th><th></th></tr></thead>
    <tbody>
    @forelse($kpis as $kpi)
    <tr class="table-row">
        <td class="px-4 py-3 font-medium text-slate-800">{{ $kpi->name }}</td>
        <td class="px-4 py-3"><span class="badge badge-blue">{{ ucfirst($kpi->category) }}</span></td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $kpi->weight }}%</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ Str::limit($kpi->description, 60) }}</td>
        <td class="px-4 py-3">
            <form method="POST" action="{{ route('performance.kpis.destroy', $kpi) }}" onsubmit="return confirm('Delete this KPI?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-500 hover:text-red-700 text-xs"><i class="fas fa-trash"></i></button>
            </form>
        </td>
    </tr>
    @empty
    <tr><td colspan="5" class="text-center py-8 text-slate-400">No KPIs defined.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

<x-modal id="create-kpi" title="Add KPI">
    <form method="POST" action="{{ route('performance.kpis.store') }}" class="space-y-4">
        @csrf
        <div><label class="form-label">Name *</label><input type="text" name="name" class="form-input" required></div>
        <div><label class="form-label">Category</label>
            <select name="category" class="form-input">
                @foreach(['financial','customer','process','learning'] as $cat)<option value="{{ $cat }}">{{ ucfirst($cat) }}</option>@endforeach
            </select>
        </div>
        <div><label class="form-label">Weight (%)</label><input type="number" name="weight" class="form-input" min="0" max="100" value="10"></div>
        <div><label class="form-label">Description</label><textarea name="description" rows="2" class="form-input"></textarea></div>
        <button type="submit" class="btn-primary w-full">Save KPI</button>
    </form>
</x-modal>
@endsection