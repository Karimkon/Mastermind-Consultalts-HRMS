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

    @elseif(in_array($kind, ['word', 'sheet', 'text'], true))
        {{-- The server has no LibreOffice, so these are not converted on the
             way out. The browser reads the file itself: a .docx is a zip of
             XML and a .xlsx likewise, both of which JavaScript can unpack and
             lay out. The bytes come from the same preview route the PDF frame
             uses, so the existing permission check still governs them. --}}
        <div class="bg-white rounded-lg shadow">
            <div id="doc-status" class="p-10 text-center text-slate-500">
                <i class="fas fa-circle-notch fa-spin text-2xl text-slate-300"></i>
                <p class="text-sm mt-3">Opening {{ $file->original_name }}…</p>
            </div>
            <div id="doc-render" class="p-6 overflow-auto" style="max-height: 80vh;"></div>
        </div>

        @if($kind === 'word')
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.2/dist/docx-preview.min.js"></script>
        @elseif($kind === 'sheet')
            <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
        @endif

        <style>
            /* docx-preview draws its own page furniture; keep it inside our card. */
            #doc-render .docx-wrapper { background: transparent; padding: 0; }
            #doc-render .docx { box-shadow: none !important; margin: 0 auto; }
            #doc-render table { border-collapse: collapse; width: 100%; font-size: 0.85rem; }
            #doc-render table td, #doc-render table th { border: 1px solid #e2e8f0; padding: 4px 8px; }
            #doc-render table tr:first-child td { background: #f8fafc; font-weight: 600; }
            #doc-render pre { white-space: pre-wrap; word-break: break-word; font-size: 0.85rem; color: #334155; }
        </style>

        <script>
        (function () {
            var kind   = @json($kind);
            var src    = @json($src);
            var dl     = @json($dl);
            var name   = @json($file->original_name);
            var status = document.getElementById('doc-status');
            var target = document.getElementById('doc-render');

            function failed(why) {
                status.innerHTML =
                    '<i class="fas fa-triangle-exclamation text-3xl text-amber-400"></i>' +
                    '<h2 class="text-base font-semibold text-slate-700 mt-3">This file could not be displayed</h2>' +
                    '<p class="text-sm text-slate-500 mt-2">' + why + '</p>' +
                    '<a href="' + dl + '" class="btn-primary mt-5 inline-block">' +
                    '<i class="fas fa-download mr-1"></i> Download ' + name + '</a>';
            }

            fetch(src, { credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) throw new Error('The server returned ' + r.status + '.');
                    return kind === 'text' ? r.text() : r.arrayBuffer();
                })
                .then(function (data) {
                    if (kind === 'word') {
                        if (typeof docx === 'undefined') throw new Error('The document viewer did not load.');
                        return docx.renderAsync(data, target, null, {
                            inWrapper: true,
                            ignoreWidth: false,
                            experimental: true,
                        });
                    }

                    if (kind === 'sheet') {
                        if (typeof XLSX === 'undefined') throw new Error('The spreadsheet viewer did not load.');
                        var book = XLSX.read(data, { type: 'array' });
                        target.innerHTML = book.SheetNames.map(function (sheet) {
                            return '<h3 class="text-sm font-semibold text-slate-600 mt-6 mb-2 first:mt-0">' +
                                   sheet + '</h3>' +
                                   XLSX.utils.sheet_to_html(book.Sheets[sheet]);
                        }).join('');
                        return;
                    }

                    var pre = document.createElement('pre');
                    pre.textContent = data;
                    target.appendChild(pre);
                })
                .then(function () { status.remove(); })
                .catch(function (e) { failed(e.message || 'The file could not be read.'); });
        })();
        </script>

    @else
        {{-- Only the formats nothing can open in a page land here: legacy
             binary .doc and .xls, and PowerPoint. --}}
        <div class="bg-white rounded-lg shadow p-10 text-center">
            <i class="fas fa-file-circle-question text-5xl text-slate-300"></i>
            <h2 class="text-lg font-semibold text-slate-700 mt-4">This format cannot be shown in the browser</h2>
            <p class="text-sm text-slate-500 mt-2 max-w-md mx-auto">
                <span class="font-medium">{{ $file->original_name }}</span> is {{ $label }}.
                PDFs, images, Word (.docx), Excel (.xlsx) and text files all preview here — this one has to be opened
                in its own application.
            </p>
            <a href="{{ $dl }}" class="btn-primary mt-6 inline-block">
                <i class="fas fa-download mr-1"></i> Download {{ $file->original_name }}
            </a>
            <p class="text-xs text-slate-400 mt-4">
                Re-saving it as a PDF or .docx before publishing will let everyone preview it here.
            </p>
        </div>
    @endif

</div>

@endsection
