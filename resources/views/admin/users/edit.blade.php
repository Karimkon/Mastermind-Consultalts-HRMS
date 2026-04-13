@extends('layouts.app')
@section('title', 'Edit User')
@section('content')
<x-page-header title="Edit User" :subtitle="$user->name">
    <a href="{{ route('admin.users.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('admin.users.update', $user) }}" class="max-w-lg">
    @csrf @method('PUT')
    <div class="card p-6 space-y-4">
        <div><label class="form-label">Full Name *</label><input type="text" name="name" class="form-input" required value="{{ old('name', $user->name) }}"></div>
        <div><label class="form-label">Email *</label><input type="email" name="email" class="form-input" required value="{{ old('email', $user->email) }}"></div>
        <div><label class="form-label">New Password <span class="text-slate-400 font-normal">(leave blank to keep current)</span></label><input type="password" name="password" class="form-input"></div>
        <div><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-input"></div>
        <div><label class="form-label">Role *</label>
            <select name="role" class="form-input" required>
                @foreach($roles as $r)<option value="{{ $r->name }}" @selected(old('role',$user->roles->first()?->name)===$r->name)>{{ str_replace('-',' ',ucfirst($r->name)) }}</option>@endforeach
            </select>
        </div>
        <div><label class="form-label">Status</label>
            <select name="status" class="form-input">
                @foreach(['active','inactive','suspended'] as $s)<option value="{{ $s }}" @selected(old('status',$user->status)===$s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Update User</button>
    </div>
</form>
@endsection