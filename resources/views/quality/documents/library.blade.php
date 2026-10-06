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
            {{-- There is no approver to choose any more. This used to be a list
                 of every account in the system, and nothing stopped the person
                 submitting from picking themselves. It goes to the CEO and the
                 Admin, and either of them can approve it. --}}
            <div class="flex items-end">
                <p class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
                    <i class="fas fa-user-shield mr-1"></i>
                    Goes to the <strong>CEO</strong> and the <strong>Admin</strong>.
                    Either one can approve it.
                </p>
            </div>
        </div>

        <x-file-drop name="file" label="Document" :required="true" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-400 flex-1 min-w-[240px]">
                <i class="fas fa-circle-info mr-1"></i>
                This does not publish it. The document goes to the CEO and the Admin, and
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
            <div class="flex items-center gap-3">
                <span class="text-xs text-amber-800">{{ str_replace('_', ' ', $doc->status) }}</span>
                {{-- Taking it back. A document sent by mistake, or sent before
                     it was finished, had no way back and had to be approved or
                     rejected before it could be corrected. --}}
                @if(in_array($doc->status, ['pending_approval', 'in_review']))
                <form method="POST" action="{{ route('quality.documents.transition', $doc) }}">
                    @csrf
                    <input type="hidden" name="action" value="recall">
                    <button class="text-xs font-semibold text-amber-900 underline">Recall</button>
                </form>
                @elseif($doc->status === 'draft')
                <form method="POST" action="{{ route('quality.documents.transition', $doc) }}">
                    @csrf
                    <input type="hidden" name="action" value="submit_approval">
                    <button class="text-xs font-semibold text-emerald-700 underline">Resubmit</button>
                </form>
                @endif
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif
@endif

{{-- Approved and not yet out. The publish button lived on each document's own
     page and nobody found it, so approved documents sat there. --}}
@if($canPublish && $readyToPublish->isNotEmpty())
<div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4">
    <p class="text-sm font-semibold text-emerald-900 mb-2">
        <i class="fas fa-circle-check mr-1"></i>
        Approved and ready to publish ({{ $readyToPublish->count() }})
    </p>
    <ul class="divide-y divide-emerald-200/60">
        @foreach($readyToPublish as $doc)
        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
            <div>
                <a href="{{ route('quality.documents.show', $doc) }}" class="text-sm font-medium text-emerald-900 underline">{{ $doc->title }}</a>
                <span class="text-xs text-emerald-700/80">
                    &middot; {{ $doc->doc_number }}
                    &middot; {{ $cats[$doc->category] ?? $doc->category }}
                    @if($doc->initiator) &middot; raised by {{ $doc->initiator->name }} @endif
                </span>
            </div>
            <form method="POST" action="{{ route('quality.documents.transition', $doc) }}">
                @csrf
                <input type="hidden" name="action" value="publish">
                <button class="btn-primary text-xs px-3 py-1.5"><i class="fas fa-upload mr-1"></i> Publish</button>
            </form>
        </li>
        @endforeach
    </ul>
</div>
@endif

<form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    <a href="{{ route('quality.documents.library') }}" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ ! $category ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">All</a>
    @foreach($cats as $k => $v)
    <a href="{{ route('quality.documents.library', ['category' => $k]) }}" class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $category === $k ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $v }}</a>
    @endforeach
</form>

{{-- A list, grouped by the category each document was filed under.

     It was a grid of cards sorted by publication date, so a policy issued in
     March sat between two forms and the categories existed only as a filter.
     Approved documents stay in the category they were given, and the headings
     below are that category. --}}
@php($grouped = $documents->getCollection()->groupBy('category'))

<div class="space-y-6">
    @forelse($grouped as $categoryKey => $inCategory)
    <div class="rounded-xl bg-white border border-slate-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-200">
            <h3 class="font-semibold text-slate-700">{{ $cats[$categoryKey] ?? $categoryKey }}</h3>
            <span class="text-xs text-slate-400">{{ $inCategory->count() }}</span>
        </div>

        <ul class="divide-y divide-slate-100">
            @foreach($inCategory as $doc)
            <li class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50">
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-slate-800">{{ $doc->title }}</p>
                    <p class="text-xs text-slate-400">
                        <span class="font-mono">{{ $doc->doc_number }}</span>
                        &middot; published {{ $doc->published_at?->format('d M Y') }}
                        @if($doc->currentFile()) &middot; {{ $doc->currentFile()->original_name }} @endif
                    </p>
                    @if($doc->description)<p class="text-sm text-slate-500 mt-0.5 line-clamp-1">{{ $doc->description }}</p>@endif
                </div>

                <div class="flex items-center gap-3 whitespace-nowrap">
                    @if($doc->currentFile())
                    {{-- Preview opens it in the browser; Download saves it. The
                         library offered only the second, so finding the right
                         policy meant saving several and opening each. --}}
                    <a href="{{ route('quality.documents.preview', ['document' => $doc->id, 'file' => $doc->currentFile()->id]) }}"
                       target="_blank" rel="noopener"
                       class="text-sm text-blue-600 font-medium"><i class="fas fa-eye mr-1"></i> Preview</a>
                    <a href="{{ route('quality.documents.download', ['document' => $doc->id, 'file' => $doc->currentFile()->id]) }}"
                       data-download-progress data-filename="{{ $doc->currentFile()->original_name }}"
                       class="text-sm text-emerald-600 font-medium"><i class="fas fa-download mr-1"></i> Download</a>
                    @else
                    <span class="text-xs text-slate-300">no file</span>
                    @endif

                    {{-- Correcting or withdrawing what the whole company reads
                         belongs to the CEO and the Admin. The EHS Officer can
                         ask for a removal but not make one. --}}
                    @if($canManage)
                    <a href="{{ route('quality.documents.show', $doc) }}" class="text-sm text-slate-500"><i class="fas fa-pen mr-1"></i> Manage</a>
                    <form method="POST" action="{{ route('quality.documents.transition', $doc) }}"
                          onsubmit="return confirm('Remove {{ addslashes($doc->title) }} from Company Documents? Every member of staff will stop seeing it.')">
                        @csrf
                        <input type="hidden" name="action" value="remove">
                        <button class="text-sm text-red-600"><i class="fas fa-trash mr-1"></i> Remove</button>
                    </form>
                    @elseif($canPublish)
                    <form method="POST" action="{{ route('quality.documents.transition', $doc) }}"
                          onsubmit="return confirm('Ask the CEO or the Admin to remove {{ addslashes($doc->title) }}?')">
                        @csrf
                        <input type="hidden" name="action" value="request_removal">
                        <button class="text-sm text-amber-600"><i class="fas fa-flag mr-1"></i> Request removal</button>
                    </form>
                    @endif
                </div>
            </li>
            @endforeach
        </ul>
    </div>
    @empty
    <div class="rounded-xl border-2 border-dashed border-slate-200 bg-white p-10 text-center text-slate-400">
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
