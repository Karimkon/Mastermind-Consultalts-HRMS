@extends('layouts.app')
@section('title','Roles & Permissions')
@section('content')
<x-page-header title="Roles & Permissions" subtitle="Manage what each role can access">
</x-page-header>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($roles as $role)
    <div class="card p-5">
        <div class="flex items-start justify-between mb-3">
            <div>
                <h3 class="font-semibold text-slate-800 capitalize">{{ str_replace('-',' ',ucfirst($role->name)) }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ $role->users_count }} user(s) · {{ $role->permissions_count }} permission(s)</p>
            </div>
            <a href="{{ route('admin.roles.edit', $role) }}" class="btn-secondary text-xs py-1.5 px-3"><i class="fas fa-edit mr-1"></i>Edit</a>
        </div>
        <div class="flex flex-wrap gap-1">
            @foreach($role->permissions->take(6) as $perm)
            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ $perm->name }}</span>
            @endforeach
            @if($role->permissions->count() > 6)
            <span class="text-xs text-slate-400">+{{ $role->permissions->count() - 6 }} more</span>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
