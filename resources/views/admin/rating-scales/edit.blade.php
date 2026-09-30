@extends('layouts.app')
@section('title', $scale->exists ? 'Edit Rating Scale' : 'New Rating Scale')

@section('content')
@php
    $max = old('max_points', $scale->max_points ?: 5);

    // Built here rather than inline in @json below: Blade cannot parse a
    // multi-line arrow function inside that directive.
    $bandRows = old('bands');
    if (! $bandRows) {
        $bandRows = $scale->exists
            ? $scale->bands->map(fn($b) => [
                  'label'       => $b->label,
                  'min_percent' => $b->min_percent,
                  'range_label' => $b->range_label,
              ])->values()->all()
            : [];
    }
@endphp

<div class="mb-4">
    <a href="{{ route('admin.rating-scales.index') }}" class="text-sm text-blue-600 hover:underline">
        <i class="fas fa-arrow-left"></i> Rating Scales
    </a>
</div>

@include('admin.blog.partials.flash')

<form method="POST"
      action="{{ $scale->exists ? route('admin.rating-scales.update', $scale) : route('admin.rating-scales.store') }}"
      x-data="scaleForm({{ (int) $max }})">
    @csrf
    @if($scale->exists) @method('PUT') @endif

    <div class="card p-5 mb-4">
        <h2 class="font-semibold text-slate-800 mb-3">
            {{ $scale->exists ? 'Edit scale' : 'New scale' }}
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-5">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                <input type="text" name="name" class="form-input text-sm"
                       value="{{ old('name', $scale->name) }}" placeholder="e.g. Standard 1-5" required>
            </div>

            <div class="sm:col-span-5">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                <input type="text" name="description" class="form-input text-sm"
                       value="{{ old('description', $scale->description) }}"
                       placeholder="Who this scale is for">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Points</label>
                {{-- Changing this adds or removes band rows immediately, so the
                     scale and its bands can never be saved disagreeing about how
                     many steps there are. --}}
                <select name="max_points" class="form-input text-sm" x-model.number="points" @change="sync()">
                    @for($p = 2; $p <= 10; $p++)
                        <option value="{{ $p }}" @selected((int) $max === $p)>{{ $p }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <label class="inline-flex items-center gap-2 mt-3 text-sm text-slate-600">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="rounded"
                   @checked(old('is_active', $scale->exists ? $scale->is_active : true))>
            Active
        </label>
    </div>

    <div class="card p-5 mb-4">
        <h2 class="font-semibold text-slate-800">Bands</h2>
        <p class="text-xs text-slate-500 mb-3">
            One row per point. <strong>Minimum %</strong> is the lowest overall result that
            lands in that band - the highest band a result clears is the one it gets.
        </p>

        <div class="space-y-2">
            <template x-for="(band, i) in bands" :key="i">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <div class="col-span-1 text-center">
                        <span class="inline-flex w-7 h-7 items-center justify-center rounded-full
                                     bg-blue-50 text-blue-700 text-xs font-bold" x-text="i + 1"></span>
                    </div>
                    <div class="col-span-5">
                        <input type="text" :name="`bands[${i}][label]`" x-model="band.label"
                               class="form-input text-sm" placeholder="e.g. Good" required>
                    </div>
                    <div class="col-span-3">
                        <div class="relative">
                            <input type="number" step="0.01" min="0" max="100"
                                   :name="`bands[${i}][min_percent]`" x-model="band.min_percent"
                                   class="form-input text-sm pr-7" required>
                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">%</span>
                        </div>
                    </div>
                    <div class="col-span-3">
                        <input type="text" :name="`bands[${i}][range_label]`" x-model="band.range_label"
                               class="form-input text-sm" placeholder="e.g. 66 - 75%">
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button class="btn-primary text-sm">
            <i class="fas fa-floppy-disk mr-1"></i> Save scale
        </button>

        @if($scale->exists && ! $scale->is_default)
            <button type="submit" form="deleteScale" class="text-sm text-red-600 hover:underline">
                Delete this scale
            </button>
        @endif
    </div>
</form>

@if($scale->exists && ! $scale->is_default)
    <form method="POST" action="{{ route('admin.rating-scales.destroy', $scale) }}" id="deleteScale" class="hidden">
        @csrf @method('DELETE')
    </form>
@endif

@push('scripts')
<script>
    function scaleForm(initialPoints) {
        return {
            points: initialPoints,
            bands: @json($bandRows),

            init() { this.sync(); },

            // Rows follow the points setting: added blank, removed from the end.
            // Existing rows are never rewritten, so editing the top of a scale
            // does not wipe labels somebody already typed.
            sync() {
                while (this.bands.length < this.points) {
                    this.bands.push({ label: '', min_percent: 0, range_label: '' });
                }
                this.bands.length = this.points;
            },
        };
    }
</script>
@endpush
@endsection
