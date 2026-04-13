@extends('layouts.app')
@section('title', 'Training Report')
@section('content')
<x-page-header title="Training Report" subtitle="Course completions and certifications"/>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="card p-4 text-center"><p class="text-3xl font-bold text-blue-600">{{ $stats['total_enrollments'] }}</p><p class="text-xs text-slate-500 mt-1">Total Enrollments</p></div>
    <div class="card p-4 text-center"><p class="text-3xl font-bold text-green-600">{{ $stats['completed'] }}</p><p class="text-xs text-slate-500 mt-1">Completed</p></div>
    <div class="card p-4 text-center"><p class="text-3xl font-bold text-indigo-600">{{ $stats['certifications'] }}</p><p class="text-xs text-slate-500 mt-1">Certifications</p></div>
</div>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Course</th><th>Category</th><th>Progress</th><th>Status</th><th>Completed</th></tr></thead>
    <tbody>
    @forelse($enrollments as $en)
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <img src="{{ $en->employee->avatar_url }}" class="w-7 h-7 rounded-full">
                <span class="text-sm font-medium text-slate-800">{{ $en->employee->full_name }}</span>
            </div>
        </td>
        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $en->course->title ?? '—' }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst($en->course->category ?? '') }}</td>
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="h-1.5 w-16 bg-slate-100 rounded-full"><div class="h-1.5 bg-blue-500 rounded-full" style="width:{{ $en->progress_pct }}%"></div></div>
                <span class="text-xs">{{ $en->progress_pct }}%</span>
            </div>
        </td>
        <td class="px-4 py-3"><span class="badge {{ $en->status === 'completed' ? 'badge-green' : 'badge-blue' }}">{{ ucfirst($en->status) }}</span></td>
        <td class="px-4 py-3 text-xs text-slate-500">{{ $en->completed_at ? \Carbon\Carbon::parse($en->completed_at)->format('M d, Y') : '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center py-8 text-slate-400">No training data found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $enrollments->withQueryString()->links() }}</div>
@endsection