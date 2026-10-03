@extends('layouts.app')
@section('title', 'Company Documents')
@section('content')

@php($cats = \App\Models\QualityDocument::CATEGORIES)

<x-page-header title="Company Documents"
               subtitle="{{ $canUpload
                    ? 'Approved Mastermind documents. You can submit new ones for approval.'
                    : 'Approved Mastermind documents, available to all staff' }}">
    @if($canUpload)
    {{-- Toggled with .hidden rather than a style, so it works whether or not
         Alpine has booted. --}}
    <button type="button" onclick="toggleLibraryUpload()"
            class="btn-primary"><i class="fas fa-paperclip mr-1"></i> Add a document</button>
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Description (optional)</label>
                <textarea name="description" rows="2" class="form-input">{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="form-label" for="library-approver">Send to for approval</label>
                {{-- Searchable: there are over a thousand accounts, and scrolling
                     a list that long to find one person is not a picker. --}}
                <select name="approver_id" id="library-approver" required class="form-select">
                    <option value="">Choose an approver</option>
                    @foreach($approvers as $u)
                        <option value="{{ $u->id }}" @selected(old('approver_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Type to search. It cannot be you.</p>
            </div>
        </div>

        <x-file-drop name="file" label="Document" :required="true" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-400 flex-1 min-w-[240px]">
                <i class="fas fa-circle-info mr-1"></i>
                This does not publish it. The document goes to your approver, and
                appears in Company Documents once it is approved and published.
            </p>
            <button class="btn-primary whitespace-nowrap"><i class="fas fa-paper-plane mr-1"></i> Send for approval</button>
        </div>
    </form>
</div>

{{-- What this person has in flight. Without it, sending a document for approval
     looks like nothing happened: it is not in the library and will not be until
     somebody else acts. --}}
@if($awaiting->isNotEmpty())
<div class="mb-6 rounded-xl bg-amber-50 border border-amber-200 p-4">
    <p class="text-sm font-semibold text-amber-900 mb-2">
        <i class="fas fa-hourglass-half mr-1"></i> Your documents waiting on approval
    </p>
    <ul class="divide-y divide-amber-200/60">
        @foreach($awaiting as $doc)
        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
            <div>
                <a href="{{ route('quality.documents.show', $doc) }}" class="text-sm font-medium text-amber-900 underline">{{ $doc->title }}</a>
                <span class="text-xs text-amber-700/80">&middot; {{ $doc->doc_number }}</span>
            </div>
            <span class="text-xs text-amber-800">
                {{ str_replace('_', ' ', $doc->status) }}
                @if($doc->approver) &middot; with {{ $doc->approver->name }} @endif
            </span>
        </li>
        @endforeach
    </ul>
</div>
@endif
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
        @if($canUpload)<p class="text-xs mt-1">Add one to start the library.</p>@endif
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $documents->links() }}</div>

<x-transfer-progress />

@if($canUpload)
@push('scripts')
<script>
// select2 measures the element when it initialises, and this panel starts
// hidden — initialising it then gives a box zero pixels wide. So it is set up
// the first time the panel is opened, with an explicit width rather than the
// measured one.
function toggleLibraryUpload() {
    var panel = document.getElementById('library-upload');
    if (!panel) return;

    panel.hidden = !panel.hidden;
    if (panel.hidden) return;

    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    if (window.jQuery && jQuery.fn.select2 && !panel.dataset.pickerReady) {
        jQuery('#library-approver').select2({
            theme: 'classic',
            width: '100%',
            placeholder: 'Search by name',
            allowClear: true,
        });
        panel.dataset.pickerReady = '1';
    }
}

// A validation error reopens the panel on load, so wire it up then too.
document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('library-upload');
    if (panel && !panel.hidden) {
        panel.hidden = true;        // toggleLibraryUpload flips it back open
        toggleLibraryUpload();
    }
});
</script>
@endpush
@endif

@endsection
