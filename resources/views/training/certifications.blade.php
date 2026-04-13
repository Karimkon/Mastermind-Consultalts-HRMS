@extends('layouts.app')
@section('title', 'Certifications')
@section('content')
<x-page-header title="Certifications" subtitle="Employee certifications tracker">
    <button @click="$dispatch('open-modal-add-cert')" class="btn-primary"><i class="fas fa-plus mr-1"></i> Add Certification</button>
</x-page-header>

<x-filter-bar :action="route('training.certifications')">
    <div class="w-52"><select name="employee_id" class="form-input w-full select2"><option value="">All Employees</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id')==$e->id)>{{ $e->full_name }}</option>@endforeach</select></div>
</x-filter-bar>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Certification</th><th>Issued By</th><th>Issue Date</th><th>Expiry</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($certifications as $cert)
    @php $expired = $cert->expiry_date && \Carbon\Carbon::parse($cert->expiry_date)->isPast(); @endphp
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <img src="{{ $cert->employee->avatar_url }}" class="w-7 h-7 rounded-full">
                <span class="text-sm font-medium text-slate-800">{{ $cert->employee->full_name }}</span>
            </div>
        </td>
        <td class="px-4 py-3 font-medium text-slate-800">{{ $cert->name }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $cert->issued_by }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $cert->issue_date }}</td>
        <td class="px-4 py-3 text-sm {{ $expired ? 'text-red-500 font-medium' : 'text-slate-600' }}">{{ $cert->expiry_date ?? '—' }}</td>
        <td class="px-4 py-3"><span class="badge {{ $expired ? 'badge-red' : 'badge-green' }}">{{ $expired ? 'Expired' : 'Valid' }}</span></td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center py-8 text-slate-400">No certifications found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

<x-modal id="add-cert" title="Add Certification">
    <form method="POST" action="{{ route('training.certifications.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div><label class="form-label">Employee *</label><select name="employee_id" class="form-input select2" required><option value="">Select employee</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select></div>
        <div><label class="form-label">Certification Name *</label><input type="text" name="name" class="form-input" required></div>
        <div><label class="form-label">Issued By</label><input type="text" name="issued_by" class="form-input"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="form-label">Issue Date</label><input type="date" name="issue_date" class="form-input"></div>
            <div><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-input"></div>
        </div>
        <div><label class="form-label">Document</label><input type="file" name="document" class="form-input"></div>
        <button type="submit" class="btn-primary w-full">Save</button>
    </form>
</x-modal>
@endsection