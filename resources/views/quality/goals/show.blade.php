@extends('layouts.app')
@section('title', $goal->reference)
@section('content')

@php($statColor = ['assigned'=>'slate','in_progress'=>'amber','completed'=>'emerald','cancelled'=>'slate','overdue'=>'red'])
@php($canAct = auth()->id() === $goal->assignee_id || auth()->user()->hasAnyRole(['super-admin','hr-admin','quality-manager']))

<x-page-header title="{{ $goal->reference }}" subtitle="{{ $goal->title }}">
    <a href="{{ route('quality.goals.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if(session('success'))<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$goal->displayStatus()] ?? 'slate' }}-100 text-{{ $statColor[$goal->displayStatus()] ?? 'slate' }}-700">{{ str_replace('_',' ', $goal->displayStatus()) }}</span>
            </div>
            @if($goal->description)<p class="text-slate-700 whitespace-pre-line">{{ $goal->description }}</p>@endif
            <dl class="grid grid-cols-2 gap-3 mt-4 text-sm">
                <div><dt class="text-slate-400">Assigned by</dt><dd class="text-slate-700">{{ $goal->initiator?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-400">Assignee</dt><dd class="text-slate-700">{{ $goal->assignee?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-400">Start</dt><dd class="text-slate-700">{{ $goal->start_date?->format('d M Y') ?? '-' }}</dd></div>
                <div><dt class="text-slate-400">Due</dt><dd class="text-slate-700">{{ $goal->end_date?->format('d M Y') ?? '-' }}</dd></div>
            </dl>
            @if($goal->progress_notes)<div class="mt-3 pt-3 border-t border-slate-100"><p class="text-xs text-slate-400">Progress notes</p><p class="text-sm text-slate-600 whitespace-pre-line">{{ $goal->progress_notes }}</p></div>@endif
        </div>

        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Attachments</h3>
            <div class="divide-y divide-slate-100">
                @forelse($goal->files as $f)
                <div class="flex items-center justify-between py-2.5">
                    <p class="text-sm text-slate-700">{{ $f->original_name }} <span class="text-xs text-slate-400">&middot; {{ $f->uploader?->name }}</span></p>
                    <a href="{{ route('quality.goals.download', ['goal'=>$goal->id,'file'=>$f->id]) }}" data-download-progress data-filename="{{ $f->original_name }}" class="text-sm text-emerald-600 font-medium"><i class="fas fa-download mr-1"></i>Download</a>
                </div>
                @empty
                <p class="text-sm text-slate-400 py-2">No attachments.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div>
        @if($canAct && !in_array($goal->status, ['completed','cancelled']))
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Update progress</h3>
            <form action="{{ route('quality.goals.update', $goal) }}" method="POST" enctype="multipart/form-data" class="space-y-3" data-upload-progress>
                @csrf @method('PUT')
                <div><label class="form-label">Status</label><select name="status" class="form-select">
                    @foreach(['assigned'=>'Assigned','in_progress'=>'In progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$v)
                    <option value="{{ $k }}" {{ $goal->status === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select></div>
                <div><label class="form-label">Progress notes</label><textarea name="progress_notes" rows="3" class="form-input">{{ $goal->progress_notes }}</textarea></div>
                <div><x-file-drop name="files[]" label="Attach evidence" :multiple="true" /></div>
                <button class="btn-primary w-full justify-center">Save</button>
            </form>
        </div>
        @else
        <div class="rounded-xl bg-white border border-slate-200 p-5 text-sm text-slate-500">
            {{ in_array($goal->status, ['completed','cancelled']) ? 'This goal is '.$goal->status.'.' : 'View only.' }}
        </div>
        @endif
    </div>
</div>

<x-transfer-progress />

@endsection
