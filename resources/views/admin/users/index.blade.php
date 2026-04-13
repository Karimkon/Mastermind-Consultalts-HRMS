@extends('layouts.app')
@section('title', 'User Management')
@section('content')
<x-page-header title="User Management" subtitle="System users and roles">
    <a href="{{ route('admin.users.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New User</a>
</x-page-header>

<x-filter-bar :action="route('admin.users.index')">
    <div class="flex-1"><input type="text" name="search" value="{{ request('search') }}" placeholder="Search name/email..." class="form-input w-full"></div>
    <div class="w-40"><select name="role" class="form-input w-full"><option value="">All Roles</option>@foreach($roles as $r)<option value="{{ $r->name }}" @selected(request('role')===$r->name)>{{ str_replace('-',' ',ucfirst($r->name)) }}</option>@endforeach</select></div>
    <div class="w-36"><select name="status" class="form-input w-full"><option value="">All Status</option>@foreach(['active','inactive','suspended'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
</x-filter-bar>

<x-data-table>
    <thead><tr class="table-header"><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($users as $user)
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-3">
                <img src="{{ $user->avatar_url }}" class="w-8 h-8 rounded-full object-cover">
                <span class="font-medium text-slate-800">{{ $user->name }}</span>
            </div>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $user->email }}</td>
        <td class="px-4 py-3">
            @foreach($user->roles as $role)
            <span class="badge badge-indigo text-xs">{{ str_replace('-',' ',ucfirst($role->name)) }}</span>
            @endforeach
        </td>
        <td class="px-4 py-3"><span class="badge {{ $user->status === 'active' ? 'badge-green' : ($user->status === 'suspended' ? 'badge-red' : 'badge-slate') }}">{{ ucfirst($user->status) }}</span></td>
        <td class="px-4 py-3 text-xs text-slate-500">{{ $user->created_at->format('M d, Y') }}</td>
        <td class="px-4 py-3 flex gap-2">
            <a href="{{ route('admin.users.edit', $user) }}" class="btn-xs btn-amber">Edit</a>
            @if($user->id !== auth()->id())
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-xs bg-red-50 text-red-600 hover:bg-red-100 rounded px-2 py-1 text-xs">Del</button>
            </form>
            @endif
        </td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center py-10 text-slate-400">No users found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $users->withQueryString()->links() }}</div>
@endsection