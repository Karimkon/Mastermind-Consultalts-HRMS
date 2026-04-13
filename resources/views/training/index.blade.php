@extends('layouts.app')
@section('title', 'Training')
@section('content')
<x-page-header title="Training Courses" subtitle="Employee learning and development">
    @can('manage-training')
    <a href="{{ route('training.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New Course</a>
    @endcan
</x-page-header>

<x-filter-bar :action="route('training.index')">
    <div class="flex-1"><input type="text" name="search" value="{{ request('search') }}" placeholder="Search courses..." class="form-input w-full"></div>
    <div class="w-40"><select name="category" class="form-input w-full"><option value="">All Categories</option>@foreach(['technical','soft-skills','compliance','leadership','safety'] as $cat)<option value="{{ $cat }}" @selected(request('category')===$cat)>{{ ucfirst(str_replace('-',' ',$cat)) }}</option>@endforeach</select></div>
</x-filter-bar>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($courses as $course)
    <div class="card p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start gap-3 mb-3">
            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-book text-indigo-600"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-slate-800 text-sm truncate">{{ $course->title }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ ucfirst(str_replace('-',' ',$course->category)) }}</p>
            </div>
        </div>
        <p class="text-xs text-slate-600 mb-3 line-clamp-2">{{ $course->description }}</p>
        <div class="flex items-center justify-between text-xs text-slate-500">
            <span><i class="fas fa-clock mr-1"></i>{{ $course->duration_hours }}h</span>
            <span><i class="fas fa-users mr-1"></i>{{ $course->enrollments_count ?? 0 }} enrolled</span>
            <div class="flex gap-2">
                <a href="{{ route('training.show', $course) }}" class="text-blue-600 hover:underline">View</a>
                @can('manage-training')
                <form method="POST" action="{{ route('training.enroll', $course) }}" class="inline">@csrf<button type="submit" class="text-green-600 hover:underline">Enroll</button></form>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-3 card p-12 text-center text-slate-400">
        <i class="fas fa-graduation-cap text-4xl mb-3 opacity-30 block"></i>
        <p>No courses found.</p>
    </div>
    @endforelse
</div>
<div class="mt-4">{{ $courses->withQueryString()->links() }}</div>
@endsection