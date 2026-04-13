@extends('layouts.app')
@section('title','Edit Role — ' . $role->name)
@section('content')
<x-page-header title="Edit Role: {{ ucfirst($role->name) }}" subtitle="Assign permissions to this role">
    <a href="{{ route('admin.roles.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('admin.roles.update', $role) }}">@csrf @method('PUT')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    @foreach($allPermissions as $module => $permissions)
    <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-slate-800 capitalize">{{ str_replace(['_','-'],' ', $module) }}</h3>
            <label class="text-xs text-blue-600 cursor-pointer">
                <input type="checkbox" class="mr-1 module-toggle" data-module="{{ $module }}"
                    onchange="document.querySelectorAll('.perm-{{ $module }}').forEach(c=>c.checked=this.checked)">
                All
            </label>
        </div>
        <div class="space-y-2">
            @foreach($permissions as $perm)
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="rounded perm-{{ $module }}"
                    {{ in_array($perm->id, $rolePermissions) ? 'checked' : '' }}>
                <span class="text-slate-700">{{ $perm->name }}</span>
            </label>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
<div class="mt-6 flex gap-3">
    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Permissions</button>
    <a href="{{ route('admin.roles.index') }}" class="btn-secondary">Cancel</a>
</div>
</form>
@endsection
