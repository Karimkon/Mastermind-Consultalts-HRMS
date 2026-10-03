{{--
    A file field that accepts any format.

    Progressive enhancement, deliberately: the real <input type="file"> is in the
    DOM and the visible box is its <label>, so clicking and keyboard focus work
    with no JavaScript at all. The script in <x-transfer-progress> adds drag and
    drop, the chosen-file list and the size check on top of that - if it fails to
    load, the field is still a working file picker.

    No `accept` attribute, on purpose. A controlled document is whatever the
    company issues: a PDF policy, an Excel register, a CAD drawing, a scanned
    manual, a training video. The only limit is size.
--}}
@props([
    'name' => 'file',
    'label' => 'Attach file',
    'multiple' => false,
    'required' => false,
    'maxKb' => null,
    'id' => null,
])

@php
    $maxKb = $maxKb ?? \App\Models\QualityDocument::maxUploadKb();
    $fieldId = $id ?? 'file-drop-' . \Illuminate\Support\Str::random(6);
    $maxLabel = $maxKb >= 1024
        ? round($maxKb / 1024) . ' MB'
        : $maxKb . ' KB';
@endphp

<div data-file-drop data-max-kb="{{ $maxKb }}" data-max-label="{{ $maxLabel }}">
    @if($label)
        <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
    @endif

    <input type="file"
           id="{{ $fieldId }}"
           name="{{ $name }}"
           @if($multiple) multiple @endif
           @if($required) required @endif
           class="sr-only"
           data-file-drop-input>

    <label for="{{ $fieldId }}"
           data-file-drop-zone
           class="flex flex-col items-center justify-center gap-1 w-full px-4 py-6 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 text-center cursor-pointer transition-colors hover:border-emerald-400 hover:bg-emerald-50/40 focus-within:border-emerald-500">
        <i class="fas fa-cloud-arrow-up text-2xl text-slate-400" data-file-drop-icon></i>
        <span class="text-sm font-medium text-slate-600">
            Drag {{ $multiple ? 'files' : 'a file' }} here, or <span class="text-emerald-600 underline">browse</span>
        </span>
        <span class="text-xs text-slate-400">Any file format &middot; up to {{ $maxLabel }}{{ $multiple ? ' each' : '' }}</span>
    </label>

    {{-- Filled in by the script once something is chosen. --}}
    <ul data-file-drop-list class="mt-2 space-y-1 hidden"></ul>
    <p data-file-drop-error class="mt-2 text-xs text-red-600 hidden"></p>
</div>
