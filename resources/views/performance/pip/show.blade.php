@extends('layouts.app')
@section('title','PIP — ' . $pip->title)
@section('content')
<x-page-header title="Performance Improvement Plan">
    <a href="{{ route('pips.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        <div class="card p-6">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-lg font-bold text-slate-800">{{ $pip->title }}</h2>
                <span class="badge-{{ $pip->status === 'active' ? 'yellow' : ($pip->status === 'completed' ? 'green' : 'red') }}">{{ ucfirst($pip->status) }}</span>
            </div>
            <p class="text-sm text-slate-600 mb-4">{{ $pip->description }}</p>
            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                <div class="bg-slate-50 p-3 rounded-lg"><p class="text-slate-500 text-xs mb-1">Start Date</p><p class="font-semibold">{{ $pip->start_date->format('M d, Y') }}</p></div>
                <div class="bg-slate-50 p-3 rounded-lg"><p class="text-slate-500 text-xs mb-1">End Date</p><p class="font-semibold">{{ $pip->end_date->format('M d, Y') }}</p></div>
            </div>
            @if($pip->objectives)
            <h4 class="font-semibold text-slate-700 mb-2">Objectives</h4>
            <ul class="space-y-1 mb-4">
                @foreach($pip->objectives as $obj)
                <li class="flex items-start gap-2 text-sm text-slate-700"><i class="fas fa-circle text-blue-400 text-xs mt-1.5"></i>{{ $obj }}</li>
                @endforeach
            </ul>
            @endif
        </div>

        <form method="POST" action="{{ route('pips.update', $pip) }}" class="card p-6 space-y-4">@csrf @method('PUT')
            <h3 class="font-semibold text-slate-800">Update PIP</h3>
            <div><label class="form-label">Status</label>
                <select name="status" class="form-select">
                    @foreach(['active','completed','extended','cancelled'] as $s)
                    <option value="{{ $s }}" {{ $pip->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="form-label">Outcome / Notes</label><textarea name="outcome" class="form-input" rows="3">{{ $pip->outcome }}</textarea></div>
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update</button>
        </form>
    </div>
    <div class="card p-6">
        <div class="flex items-center gap-4 mb-4">
            <img src="{{ $pip->employee->avatar_url }}" class="w-14 h-14 rounded-xl object-cover">
            <div>
                <h3 class="font-semibold text-slate-800">{{ $pip->employee->full_name }}</h3>
                <p class="text-xs text-slate-500">{{ $pip->employee->designation?->title }}</p>
            </div>
        </div>
        <div class="text-sm space-y-2">
            <div class="flex justify-between"><span class="text-slate-500">Department</span><span>{{ $pip->employee->department?->name }}</span></div>
            @if($pip->cycle)<div class="flex justify-between"><span class="text-slate-500">Cycle</span><span>{{ $pip->cycle->name ?? $pip->cycle->year }}</span></div>@endif
        </div>
    </div>
</div>
@endsection
