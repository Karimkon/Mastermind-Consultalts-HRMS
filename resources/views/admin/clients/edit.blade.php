@extends('layouts.app')
@section('title', 'Edit Client')

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.clients.show', $client) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Back</a>
    <h1 class="text-2xl font-bold text-slate-800">Edit — {{ $client->company_name }}</h1>
</div>

<form method="POST" action="{{ route('admin.clients.update', $client) }}" class="max-w-2xl">
    @csrf @method('PUT')
    <div class="card p-6 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Company Name <span class="text-red-500">*</span></label>
                <input type="text" name="company_name" class="form-input" value="{{ old('company_name', $client->company_name) }}" required>
            </div>
            <div>
                <label class="form-label">Contact Person <span class="text-red-500">*</span></label>
                <input type="text" name="contact_person" class="form-input" value="{{ old('contact_person', $client->contact_person) }}" required>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Industry</label>
                <input type="text" name="industry" class="form-input" value="{{ old('industry', $client->industry) }}">
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   {{ $client->status === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $client->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
        <div>
            <label class="form-label">Address</label>
            <textarea name="address" rows="2" class="form-textarea">{{ old('address', $client->address) }}</textarea>
        </div>
        <div>
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="2" class="form-textarea">{{ old('notes', $client->notes) }}</textarea>
        </div>
    </div>
    <div class="flex gap-3 mt-4">
        <button type="submit" class="btn-primary"><i class="fas fa-save mr-2"></i>Update Client</button>
        <a href="{{ route('admin.clients.show', $client) }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
