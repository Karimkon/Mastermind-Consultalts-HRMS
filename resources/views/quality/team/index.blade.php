@extends('layouts.app')
@section('title', 'Quality Team')
@section('content')

<x-page-header title="Quality Team" subtitle="Appoint the person in charge of Quality Management" />

@if(session('success'))<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-1">Quality Manager(s)</h3>
            <p class="text-xs text-slate-400 mb-3">Runs the module: documents, audits, checks, non-conformities and goals.</p>
            <div class="divide-y divide-slate-100">
                @forelse($managers as $m)
                <div class="flex items-center justify-between py-2.5">
                    <div><p class="font-medium text-slate-800">{{ $m->name }}</p><p class="text-xs text-slate-400">{{ $m->email }}</p></div>
                    <form action="{{ route('quality.team.revoke') }}" method="POST" onsubmit="return confirm('Remove this appointment?')">@csrf<input type="hidden" name="user_id" value="{{ $m->id }}"><input type="hidden" name="role" value="quality-manager"><button class="text-xs text-red-600">Remove</button></form>
                </div>
                @empty
                <p class="text-sm text-amber-600 py-2"><i class="fas fa-triangle-exclamation mr-1"></i>No one is appointed yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-1">Auditors</h3>
            <p class="text-xs text-slate-400 mb-3">Can conduct quality audits.</p>
            <div class="divide-y divide-slate-100">
                @forelse($auditors as $a)
                <div class="flex items-center justify-between py-2.5">
                    <div><p class="font-medium text-slate-800">{{ $a->name }}</p><p class="text-xs text-slate-400">{{ $a->email }}</p></div>
                    <form action="{{ route('quality.team.revoke') }}" method="POST" onsubmit="return confirm('Remove this appointment?')">@csrf<input type="hidden" name="user_id" value="{{ $a->id }}"><input type="hidden" name="role" value="auditor"><button class="text-xs text-red-600">Remove</button></form>
                </div>
                @empty
                <p class="text-sm text-slate-400 py-2">No auditors appointed.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div>
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Appoint</h3>
            <form action="{{ route('quality.team.appoint') }}" method="POST" class="space-y-3">
                @csrf
                <div><label class="form-label">Person</label>
                    <select name="user_id" required class="form-select">
                        <option value="">Select employee</option>
                        @foreach($candidates as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div><label class="form-label">Role</label>
                    <select name="role" class="form-select"><option value="quality-manager">Quality Manager</option><option value="auditor">Auditor</option></select>
                </div>
                <button class="btn-primary w-full justify-center">Appoint</button>
            </form>
            <p class="text-xs text-slate-400 mt-3">Only the CEO or a System Admin can appoint the quality team.</p>
        </div>
    </div>
</div>

@endsection
