@extends('layouts.app')
@section('title', $document->doc_number)
@section('content')

@php($cats = \App\Models\QualityDocument::CATEGORIES)
@php($statColor = ['draft'=>'slate','in_review'=>'amber','pending_approval'=>'blue','approved'=>'emerald','published'=>'emerald','rejected'=>'red','archived'=>'slate'])
@php($uid = auth()->id())
@php($isApprover = $uid === $document->approver_id)

<x-page-header title="{{ $document->doc_number }}" subtitle="{{ $document->title }}">
    <a href="{{ url()->previous() }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if(session('success'))<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Detail --}}
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$document->status] ?? 'slate' }}-100 text-{{ $statColor[$document->status] ?? 'slate' }}-700">{{ str_replace('_',' ', $document->status) }}</span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">{{ $cats[$document->category] ?? $document->category }}</span>
                @if($document->isPublished())<span class="text-xs text-emerald-600"><i class="fas fa-globe mr-1"></i>Visible to all staff</span>@endif
            </div>
            @if($document->description)<p class="text-slate-700 whitespace-pre-line">{{ $document->description }}</p>@endif
            <dl class="grid grid-cols-2 gap-3 mt-4 text-sm">
                <div><dt class="text-slate-400">Initiator</dt><dd class="text-slate-700">{{ $document->initiator?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-400">Editor</dt><dd class="text-slate-700">{{ $document->editor?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-400">Approver</dt><dd class="text-slate-700">{{ $document->approver?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-400">Effective</dt><dd class="text-slate-700">{{ $document->start_date?->format('d M Y') ?? '-' }} &rarr; {{ $document->end_date?->format('d M Y') ?? '-' }}</dd></div>
            </dl>
        </div>

        {{-- Files / versions --}}
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Versions</h3>
            <div class="divide-y divide-slate-100 mb-4">
                @forelse($document->files as $f)
                <div class="flex items-center justify-between py-2.5">
                    <div><p class="text-sm font-medium text-slate-800">v{{ $f->version }} &middot; {{ $f->original_name }}</p><p class="text-xs text-slate-400">{{ $f->uploader?->name }} &middot; {{ $f->created_at->format('d M Y H:i') }}{{ $f->notes ? ' · '.$f->notes : '' }}</p></div>
                    <a href="{{ route('quality.documents.download', ['document'=>$document->id,'file'=>$f->id]) }}" data-download-progress data-filename="{{ $f->original_name }}" class="text-sm text-emerald-600 font-medium"><i class="fas fa-download mr-1"></i>Download</a>
                </div>
                @empty
                <p class="text-sm text-slate-400 py-2">No file uploaded yet.</p>
                @endforelse
            </div>
            @if($canEdit && $document->status !== 'published' && $document->status !== 'archived')
            <form action="{{ route('quality.documents.upload', $document) }}" method="POST" enctype="multipart/form-data" class="border-t border-slate-100 pt-4 space-y-3" data-upload-progress>
                @csrf
                <x-file-drop name="file" label="Upload new version" :required="true" />
                <div class="flex flex-wrap items-end gap-2">
                    <input name="notes" placeholder="Notes (optional)" class="form-input flex-1">
                    <button class="btn-secondary">Upload</button>
                </div>
            </form>
            @elseif(! $canEdit)
            <p class="border-t border-slate-100 pt-4 text-xs text-slate-400"><i class="fas fa-eye mr-1"></i>You have read access to this document.</p>
            @endif
        </div>

        {{-- Timeline --}}
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-3">History</h3>
            <ol class="space-y-2">
                @foreach($document->events as $e)
                <li class="flex items-start gap-3 text-sm">
                    <i class="fas fa-circle text-[6px] text-slate-300 mt-1.5"></i>
                    <div><span class="font-medium text-slate-700">{{ ucwords(str_replace('_',' ', $e->action)) }}</span>
                        <span class="text-slate-400">&middot; {{ $e->user?->name ?? 'system' }} &middot; {{ $e->created_at->format('d M Y H:i') }}</span>
                        @if($e->note)<p class="text-slate-500">{{ $e->note }}</p>@endif
                    </div>
                </li>
                @endforeach
            </ol>
        </div>
    </div>

    {{-- Workflow actions --}}
    <div class="space-y-4">
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Workflow</h3>

            @if(! $canEdit)
            <p class="text-sm text-slate-400">This document is open to you for review only. Its workflow is driven by the initiator, editor and approver named above.</p>
            @endif

            @if($canEdit && in_array($document->status, ['draft','in_review','rejected']))
            <form action="{{ route('quality.documents.transition', $document) }}" method="POST" class="space-y-2 mb-4">
                @csrf <input type="hidden" name="action" value="forward_editor">
                <label class="form-label">Forward to editor</label>
                <select name="editor_id" class="form-select"><option value="">{{ $document->editor?->name ?? 'Select editor' }}</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                <button class="btn-secondary w-full justify-center">Send for editing</button>
            </form>
            @endif

            @if($canEdit && in_array($document->status, ['in_review','draft']))
            <form action="{{ route('quality.documents.transition', $document) }}" method="POST" class="space-y-2 mb-4">
                @csrf <input type="hidden" name="action" value="submit_approval">
                <label class="form-label">Submit for approval</label>
                <select name="approver_id" class="form-select"><option value="">{{ $document->approver?->name ?? 'Select approver' }}</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                <button class="btn-secondary w-full justify-center">Send for approval</button>
            </form>
            @endif

            @if($document->status === 'pending_approval' && ($canManage || $isApprover))
            <div class="space-y-2 mb-4">
                <form action="{{ route('quality.documents.transition', $document) }}" method="POST">@csrf<input type="hidden" name="action" value="approve"><input name="note" placeholder="Approval note (optional)" class="form-input mb-2"><button class="btn-primary w-full justify-center"><i class="fas fa-check mr-1"></i>Approve</button></form>
                <form action="{{ route('quality.documents.transition', $document) }}" method="POST">@csrf<input type="hidden" name="action" value="reject"><button class="w-full justify-center px-3 py-2 rounded-lg text-sm font-medium bg-red-50 text-red-700 border border-red-200">Send back</button></form>
            </div>
            @endif

            @if($document->status === 'approved' && $canManage)
            <form action="{{ route('quality.documents.transition', $document) }}" method="POST" class="mb-4">@csrf<input type="hidden" name="action" value="publish">
                <button class="btn-primary w-full justify-center"><i class="fas fa-globe mr-1"></i>Publish to company</button>
                <p class="text-xs text-slate-400 mt-1">Makes this visible to every employee.</p>
            </form>
            @endif

            @if($document->status === 'published' && $canManage)
            <form action="{{ route('quality.documents.transition', $document) }}" method="POST">@csrf<input type="hidden" name="action" value="archive">
                <button class="w-full justify-center px-3 py-2 rounded-lg text-sm font-medium bg-slate-50 text-slate-600 border border-slate-200">Archive</button>
            </form>
            @endif

            @if($document->status === 'archived')
            <p class="text-sm text-slate-400">This document is archived.</p>
            @endif
        </div>
    </div>
</div>

<x-transfer-progress />

@endsection
