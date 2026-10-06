@extends('layouts.app')
@section('title', $document->title . ' — Preview')
@section('content')

@php
    $src = route('quality.documents.preview', ['document' => $document->id, 'file' => $file->id]);
    $dl  = route('quality.documents.download', ['document' => $document->id, 'file' => $file->id]);
@endphp

<div class="max-w-6xl mx-auto">

    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <a href="{{ route('quality.documents.library') }}" class="text-sm text-slate-500 hover:text-slate-700">
                <i class="fas fa-arrow-left mr-1"></i> Company Documents
            </a>
            <h1 class="text-2xl font-bold text-slate-800 mt-1">{{ $document->title }}</h1>
            <p class="text-xs text-slate-500 mt-1">
                <span class="font-mono">{{ $document->doc_number }}</span>
                @if($document->category) &middot; {{ ucwords(str_replace('_', ' ', $document->category)) }} @endif
                &middot; v{{ $file->version }}
                &middot; {{ $file->original_name }}
            </p>
        </div>
        <a href="{{ $dl }}" class="btn-primary whitespace-nowrap">
            <i class="fas fa-download mr-1"></i> Download
        </a>
    </div>

    @if($kind === 'pdf')
        {{-- Embedded, not navigated to: a browser set to save PDFs rather than
             open them applies that to a navigation, not to a frame it renders
             inside a page. --}}
        <div class="bg-white rounded-lg shadow overflow-hidden" style="height: 80vh;">
            <iframe src="{{ $src }}#view=FitH"
                    title="{{ $document->title }}"
                    class="w-full h-full"
                    style="border: 0;"></iframe>
        </div>

    @elseif($kind === 'image')
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <img src="{{ $src }}" alt="{{ $document->title }}" class="max-w-full mx-auto rounded">
        </div>

    @else
        {{-- Word, Excel and the rest. No browser renders these, so say so
             rather than firing off a download the person did not ask for. --}}
        <div class="bg-white rounded-lg shadow p-10 text-center">
            <i class="fas fa-file-word text-5xl text-slate-300"></i>
            <h2 class="text-lg font-semibold text-slate-700 mt-4">This format cannot be shown in the browser</h2>
            <p class="text-sm text-slate-500 mt-2 max-w-md mx-auto">
                <span class="font-medium">{{ $file->original_name }}</span> is an Office document.
                Browsers can display PDFs and images, but not Word or Excel files — download it to open it.
            </p>
            <a href="{{ $dl }}" class="btn-primary mt-6 inline-block">
                <i class="fas fa-download mr-1"></i> Download {{ $file->original_name }}
            </a>
            <p class="text-xs text-slate-400 mt-4">
                Saving this document as a PDF before publishing it will let everyone preview it here.
            </p>
        </div>
    @endif

</div>

@endsection
