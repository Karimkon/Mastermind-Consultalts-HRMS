@extends('layouts.app')
@section('title', 'Edit Course')
@section('content')
<x-page-header title="Edit Course" :subtitle="$course->title">
    <a href="{{ route('training.show', $course) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('training.update', $course) }}" enctype="multipart/form-data" class="max-w-2xl">
    @csrf @method('PUT')
    <div class="card p-6 space-y-4">
        <div><label class="form-label">Title *</label><input type="text" name="title" class="form-input" required value="{{ old('title', $course->title) }}"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Category</label>
                <select name="category" class="form-input">
                    @foreach(['technical','soft-skills','compliance','leadership','safety'] as $cat)<option value="{{ $cat }}" @selected(old('category',$course->category)===$cat)>{{ ucfirst(str_replace('-',' ',$cat)) }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Duration (hours)</label><input type="number" name="duration_hours" class="form-input" value="{{ old('duration_hours', $course->duration_hours) }}" step="0.5"></div>
        </div>
        <div><label class="form-label">Description</label><textarea name="description" rows="4" class="form-input">{{ old('description', $course->description) }}</textarea></div>
        <div><label class="form-label">Replace Material</label><input type="file" name="material" class="form-input"></div>
        <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Update</button>
    </div>
</form>
@endsection