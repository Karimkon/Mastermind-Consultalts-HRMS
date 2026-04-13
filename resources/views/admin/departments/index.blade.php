@extends('layouts.app')
@section('title', 'Departments')
@section('content')
<x-page-header title="Departments & Designations" subtitle="Organizational structure">
    <button @click="$dispatch('open-modal-add-designation')" class="btn-secondary"><i class="fas fa-plus mr-1"></i> Add Designation</button>
    <button @click="$dispatch('open-modal-add-department')" class="btn-primary"><i class="fas fa-plus mr-1"></i> Add Department</button>
</x-page-header>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <!-- Departments -->
    <div>
        <h3 class="font-semibold text-slate-700 mb-3">Departments</h3>
        <x-data-table>
            <thead><tr class="table-header"><th>Department</th><th>Head</th><th>Employees</th><th></th></tr></thead>
            <tbody>
            @forelse($departments as $dept)
            <tr class="table-row">
                <td class="px-4 py-3">
                    <p class="font-medium text-slate-800">{{ $dept->name }}</p>
                    @if($dept->parent)<p class="text-xs text-slate-500">Under: {{ $dept->parent->name }}</p>@endif
                </td>
                <td class="px-4 py-3 text-sm text-slate-600">{{ $dept->head->full_name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-600">{{ $dept->employees_count ?? 0 }}</td>
                <td class="px-4 py-3">
                    <button onclick="editDept({{ $dept->id }}, '{{ addslashes($dept->name) }}')" class="text-amber-600 hover:text-amber-800 text-xs"><i class="fas fa-edit"></i></button>
                    <form method="POST" action="{{ route('admin.departments.destroy', $dept) }}" class="inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs ml-2"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center py-6 text-slate-400">No departments.</td></tr>
            @endforelse
            </tbody>
        </x-data-table>
    </div>
    <!-- Designations -->
    <div>
        <h3 class="font-semibold text-slate-700 mb-3">Designations</h3>
        <x-data-table>
            <thead><tr class="table-header"><th>Title</th><th>Department</th><th>Grade</th><th></th></tr></thead>
            <tbody>
            @forelse($designations as $desig)
            <tr class="table-row">
                <td class="px-4 py-3 font-medium text-slate-800">{{ $desig->title }}</td>
                <td class="px-4 py-3 text-sm text-slate-600">{{ $desig->department->name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-600">{{ $desig->grade ?? '—' }}</td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.designations.destroy', $desig) }}" class="inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center py-6 text-slate-400">No designations.</td></tr>
            @endforelse
            </tbody>
        </x-data-table>
    </div>
</div>

<!-- Add Department Modal -->
<x-modal id="add-department" title="Add Department">
    <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-4">
        @csrf
        <div><label class="form-label">Name *</label><input type="text" name="name" class="form-input" required></div>
        <div><label class="form-label">Parent Department</label>
            <select name="parent_id" class="form-input">
                <option value="">None (top-level)</option>
                @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="form-label">Department Head</label>
            <select name="head_id" class="form-input select2">
                <option value="">None</option>
                @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary w-full">Save</button>
    </form>
</x-modal>

<!-- Add Designation Modal -->
<x-modal id="add-designation" title="Add Designation">
    <form method="POST" action="{{ route('admin.designations.store') }}" class="space-y-4">
        @csrf
        <div><label class="form-label">Title *</label><input type="text" name="title" class="form-input" required></div>
        <div><label class="form-label">Department</label>
            <select name="department_id" class="form-input">
                <option value="">None</option>
                @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="form-label">Grade</label><input type="text" name="grade" class="form-input" placeholder="e.g. Grade 5"></div>
        <button type="submit" class="btn-primary w-full">Save</button>
    </form>
</x-modal>
@endsection