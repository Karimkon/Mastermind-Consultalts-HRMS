@extends('layouts.app')
@section('title', 'Company Documents')
@section('content')

@php($cats = \App\Models\QualityDocument::CATEGORIES)

<x-page-header title="Company Documents"
               subtitle="{{ $canUpload
                    ? 'Approved Mastermind documents. You maintain this library.'
                    : 'Approved Mastermind documents, available to all staff' }}">
    @if($canUpload)
    {{-- Toggled with .hidden rather than a style, so it works whether or not
         Alpine has booted. --}}
    <button type="button"
            onclick="var p=document.getElementById('library-upload'); p.hidden=!p.hidden; if(!p.hidden){p.scrollIntoView({behavior:'smooth',block:'nearest'});}"
            class="btn-primary"><i class="fas fa-paperclip mr-1"></i> Attach a document</button>
    @endif
</x-page-header>

@if(session('success'))<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

{{-- The uploader. Only the quality manager and the appointed auditors get this.
     For everybody else the panel is not rendered at all, and libraryUpload()
     refuses them regardless - the button is presentation, the controller is the
     control. --}}
@if($canUpload)
{{-- Stays open when a validation error sent them back, so the message is next to
     the form that produced it. --}}
<div id="library-upload" class="mb-6 rounded-xl bg-white border border-slate-200 p-5" @if(! $errors->any()) hidden @endif>
    <form action="{{ route('quality.documents.library.upload') }}" method="POST"
          enctype="multipart/form-data" class="space-y-4" data-upload-progress>
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Title</label>
                <input name="title" required value="{{ old('title') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    @foreach($cats as $k => $v)
                        <option value="{{ $k }}" @selected(old('category') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="form-label">Description (optional)</label>
            <textarea name="description" rows="2" class="form-input">{{ old('description') }}</textarea>
        </div>

        <x-file-drop name="file" label="Document" :required="true" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-400 flex-1 min-w-[240px]">
                <i class="fas fa-circle-info mr-1"></i>
                Published to every member of staff as soon as it uploads, without the
                editor and approver steps. Use Document Control if it needs review first.
            </p>
            <button class="btn-primary whitespace-nowrap"><i class="fas fa-cloud-arrow-up mr-1"></i> Upload</button>
        </div>
    </form>
</div>
@endif

<form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    <a href="{{ route('quality.documents.library') }}" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ ! $category ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">All</a>
    @foreach($cats as $k => $v)
    <a href="{{ route('quality.documents.library', ['category' => $k]) }}" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $category === $k ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $v }}</a>
    @endforeach
</form>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($documents as $doc)
    <div class="rounded-xl bg-white border border-slate-200 p-5 flex flex-col">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-mono text-slate-400">{{ $doc->doc_number }}</span>
            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">{{ $cats[$doc->category] ?? $doc->category }}</span>
        </div>
        <h3 class="font-semibold text-slate-800 flex-1">{{ $doc->title }}</h3>
        @if($doc->description)<p class="text-sm text-slate-500 mt-1 line-clamp-2">{{ $doc->description }}</p>@endif
        <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
            <span class="text-xs text-slate-400">Published {{ $doc->published_at?->format('d M Y') }}</span>
            @if($doc->currentFile())
            <a href="{{ route('quality.documents.download', ['document' => $doc->id, 'file' => $doc->currentFile()->id]) }}" data-download-progress data-filename="{{ $doc->currentFile()->original_name }}" class="text-sm text-emerald-600 font-medium"><i class="fas fa-download mr-1"></i> Open</a>
            @else
            <span class="text-xs text-slate-300">no file</span>
            @endif
        </div>
    </div>
    @empty
    <div class="col-span-full rounded-xl border-2 border-dashed border-slate-200 bg-white p-10 text-center text-slate-400">
        <i class="fas fa-folder-open text-3xl mb-2"></i>
        <p class="text-sm">No published documents yet.</p>
        @if($canUpload)<p class="text-xs mt-1">Attach one to start the library.</p>@endif
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $documents->links() }}</div>

<x-transfer-progress />

@endsection
