@extends("layouts.app")
@section("title","Employees")
@section("breadcrumb")<span class="text-slate-600 text-sm font-medium">Employees</span>@endsection
@section("content")
<x-page-header title="Employees" subtitle="Manage all employee records">
    <a href="{{ route('employees.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Add Employee</a>
    <a href="{{ route('export.employees') }}" class="btn-secondary"><i class="fas fa-file-excel text-green-600"></i> Export Excel</a>
    <a href="{{ route('import.form') }}" class="btn-secondary"><i class="fas fa-file-upload text-blue-600"></i> Import</a>
</x-page-header>

{{-- Filters --}}
<x-filter-bar :action="route('employees.index')">
    <div>
        <label class="form-label">Search</label>
        <input type="text" name="search" value="{{ request('search') }}" class="form-input w-56" placeholder="Name, employee number...">
    </div>
    <div>
        <label class="form-label">Department</label>
        <select name="department_id" class="form-select w-44 select2">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Status</label>
        <select name="status" class="form-select w-40">
            <option value="">All Status</option>
            <option value="active" {{ request('status')=='active'?'selected':'' }}>Active</option>
            <option value="on_leave" {{ request('status')=='on_leave'?'selected':'' }}>On Leave</option>
            <option value="terminated" {{ request('status')=='terminated'?'selected':'' }}>Terminated</option>
            <option value="suspended" {{ request('status')=='suspended'?'selected':'' }}>Suspended</option>
        </select>
    </div>
    <div>
        <label class="form-label">Type</label>
        <select name="employment_type" class="form-select w-40">
            <option value="">All Types</option>
            <option value="full_time">Full Time</option>
            <option value="part_time">Part Time</option>
            <option value="contract">Contract</option>
            <option value="intern">Intern</option>
        </select>
    </div>
</x-filter-bar>

<x-data-table>
    <thead class="bg-slate-50">
        <tr>
            <th class="table-head px-6 py-3 text-left">Employee</th>
            <th class="table-head px-4 py-3 text-left">Emp #</th>
            <th class="table-head px-4 py-3 text-left">Department</th>
            <th class="table-head px-4 py-3 text-left">Designation</th>
            <th class="table-head px-4 py-3 text-left">Type</th>
            <th class="table-head px-4 py-3 text-left">Hire Date</th>
            <th class="table-head px-4 py-3 text-left">Status</th>
            <th class="table-head px-4 py-3 text-left">Actions</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($employees as $employee)
        <tr class="table-row">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <img src="{{ $employee->avatar_url }}" class="w-9 h-9 rounded-full object-cover border-2 border-slate-200">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $employee->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $employee->user?->email }}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ $employee->emp_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $employee->department?->name ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $employee->designation?->title ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst(str_replace('_',' ', $employee->employment_type)) }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $employee->hire_date?->format('M d, Y') }}</td>
            <td class="px-4 py-3">{!! $employee->status_badge !!}</td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                    <a href="{{ route('employees.show', $employee) }}" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View"><i class="fas fa-eye text-xs"></i></a>
                    <a href="{{ route('employees.edit', $employee) }}" class="p-1.5 text-slate-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors" title="Edit"><i class="fas fa-pen text-xs"></i></a>
                    <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Archive this employee?')">@csrf @method('DELETE')
                        <button class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Archive"><i class="fas fa-archive text-xs"></i></button>
                    </form>
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="8" class="px-6 py-12 text-center text-slate-400"><i class="fas fa-users text-3xl mb-3 block text-slate-300"></i>No employees found</td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $employees->withQueryString()->links() }}</div>
@endsection
