@extends("layouts.app")
@section("title", "Managed Employees")
@section("content")
<x-page-header title="Managed Employees" subtitle="Employees across your assigned companies">
    <form class="flex gap-2" method="GET">
        <input type="text" name="search" value="{{ request("search") }}" class="form-input w-56" placeholder="Search employee...">
        <select name="status" class="form-select w-36">
            <option value="">All Status</option>
            @foreach(["active"=>"Active","on_leave"=>"On Leave","suspended"=>"Suspended"] as $v=>$l)
            <option value="{{ $v }}" {{ request("status")==$v?"selected":"" }}>{{ $l }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary"><i class="fas fa-search"></i></button>
    </form>
</x-page-header>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-left">Department</th>
        <th class="table-head px-4 py-3 text-left">Company</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
        <th class="table-head px-4 py-3 text-left">Actions</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($employees as $emp)
        @php $company = $clients->first(fn($c) => $c->employees->contains("id", $emp->id)); @endphp
        <tr class="table-row">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <img src="{{ $emp->avatar_url }}" class="w-9 h-9 rounded-full object-cover">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $emp->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $emp->emp_number }}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $emp->department?->name ?? "—" }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $company?->company_name ?? "—" }}</td>
            <td class="px-4 py-3">{!! $emp->status_badge !!}</td>
            <td class="px-4 py-3">
                <a href="{{ route("account-manager.employees.show", $emp) }}" class="btn-xs btn-blue"><i class="fas fa-eye"></i> View</a>
            </td>
        </tr>
        @empty
        <tr><td colspan="5" class="py-12 text-center text-slate-400">No employees found.</td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $employees->withQueryString()->links() }}</div>
@endsection
