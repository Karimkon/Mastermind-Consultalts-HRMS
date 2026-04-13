@extends("layouts.app")
@section("title","Salary Management")
@section("content")
<x-page-header title="Employee Salaries">
    <a href="{{ route('salary.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Assign Salary</a>
    <a href="{{ route('salary.grades') }}" class="btn-secondary"><i class="fas fa-layer-group"></i> Salary Grades</a>
    <a href="{{ route('salary.components') }}" class="btn-secondary"><i class="fas fa-list-ul"></i> Components</a>
</x-page-header>
<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-right">Basic Salary</th>
        <th class="table-head px-4 py-3 text-left">Effective From</th>
        <th class="table-head px-4 py-3 text-left">Actions</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($salaries as $sal)
        <tr class="table-row">
            <td class="px-6 py-3"><div class="flex items-center gap-3"><img src="{{ $sal->employee->avatar_url }}" class="w-8 h-8 rounded-full"><div><p class="text-sm font-medium text-slate-800">{{ $sal->employee->full_name }}</p><p class="text-xs text-slate-500">{{ $sal->employee->department?->name }}</p></div></div></td>
            <td class="px-4 py-3 text-right text-sm font-bold text-green-700">UGX {{ number_format($sal->basic_salary,2) }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $sal->effective_from->format("M d, Y") }}</td>
            <td class="px-4 py-3"><a href="{{ route('salary.edit',$sal) }}" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><i class="fas fa-pen text-xs"></i></a></td>
        </tr>
        @empty
        <tr><td colspan="4" class="py-8 text-center text-slate-400">No salaries assigned yet.</td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $salaries->links() }}</div>
@endsection
