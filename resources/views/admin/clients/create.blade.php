@extends('layouts.app')
@section('title', 'Add Client')

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.clients.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Back</a>
    <h1 class="text-2xl font-bold text-slate-800">Add New Client</h1>
</div>

<form method="POST" action="{{ route('admin.clients.store') }}" class="max-w-2xl">
    @csrf
    <div class="card p-6 space-y-5">
        <h3 class="text-base font-semibold text-slate-700 border-b border-slate-100 pb-3">Company Information</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Company Name <span class="text-red-500">*</span></label>
                <input type="text" name="company_name" class="form-input" value="{{ old('company_name') }}" required>
            </div>
            <div>
                <label class="form-label">Contact Person <span class="text-red-500">*</span></label>
                <input type="text" name="contact_person" class="form-input" value="{{ old('contact_person') }}" required>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Industry</label>
                <input type="text" name="industry" class="form-input" value="{{ old('industry') }}" placeholder="e.g. Technology">
            </div>
        </div>

        <div>
            <label class="form-label">Address</label>
            <textarea name="address" rows="2" class="form-textarea">{{ old('address') }}</textarea>
        </div>

        <div>
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="2" class="form-textarea">{{ old('notes') }}</textarea>
        </div>

        <h3 class="text-base font-semibold text-slate-700 border-b border-slate-100 pb-3 pt-2">Portal Login Credentials</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" class="form-input" value="{{ old('email') }}" required>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" class="form-input" required minlength="8">
            </div>
            <div>
                <label class="form-label">Confirm Password <span class="text-red-500">*</span></label>
                <input type="password" name="password_confirmation" class="form-input" required>
            </div>
        </div>
    </div>

    <div class="flex gap-3 mt-4">
        <button type="submit" class="btn-primary"><i class="fas fa-save mr-2"></i>Create Client</button>
        <a href="{{ route('admin.clients.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
