@extends('layouts.app')
@section('title', $course->title)
@section('content')
<x-page-header :title="$course->title" subtitle="Course Details">
    <a href="{{ route('training.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
    @can('manage-training')
    <a href="{{ route('training.edit', $course) }}" class="btn-secondary"><i class="fas fa-edit mr-1"></i> Edit</a>
    @endcan
</x-page-header>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-3">About This Course</h3>
            <p class="text-sm text-slate-600">{{ $course->description }}</p>
            @if($course->material_path)
            <a href="{{ Storage::url($course->material_path) }}" class="mt-3 inline-flex items-center gap-2 text-blue-600 text-sm hover:underline" target="_blank">
                <i class="fas fa-download"></i> Download Course Material
            </a>
            @endif
        </div>
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Enrolled Employees ({{ $enrollments->count() }})</h3>
            <x-data-table>
                <thead><tr class="table-header"><th>Employee</th><th>Enrolled</th><th>Progress</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($enrollments as $en)
                <tr class="table-row">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <img src="{{ $en->employee->avatar_url }}" class="w-7 h-7 rounded-full">
                            <span class="text-sm font-medium text-slate-800">{{ $en->employee->full_name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $en->created_at->format('M d, Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-20 bg-slate-100 rounded-full"><div class="h-1.5 bg-blue-500 rounded-full" style="width:{{ $en->progress_pct }}%"></div></div>
                            <span class="text-xs">{{ $en->progress_pct }}%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="badge {{ $en->status === 'completed' ? 'badge-green' : ($en->status === 'in-progress' ? 'badge-blue' : 'badge-slate') }}">{{ ucfirst($en->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center py-6 text-slate-400">No enrollments yet.</td></tr>
                @endforelse
                </tbody>
            </x-data-table>
        </div>
    </div>
    <div class="space-y-4">
        <div class="card p-5 text-sm space-y-3">
            <h3 class="font-semibold text-slate-700">Course Details</h3>
            <div class="flex justify-between"><span class="text-slate-500">Category</span><span>{{ ucfirst(str_replace('-',' ',$course->category)) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Duration</span><span>{{ $course->duration_hours }} hours</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Enrolled</span><span>{{ $enrollments->count() }}</span></div>
        </div>
    </div>
</div>
@endsection