@extends('layouts.app')
@section('title', 'Company Documents')
@section('content')

@php($cats = \App\Models\QualityDocument::CATEGORIES)

<x-page-header title="Company Documents" subtitle="Approved Mastermind documents, available to all staff" />

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
        <i class="fas fa-folder-open text-3xl mb-2"></i><p class="text-sm">No published documents yet.</p>
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $documents->links() }}</div>

<x-transfer-progress />

@endsection
