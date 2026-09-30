@extends('layouts.app')
@section('title','PIP — ' . $pip->title)
@section('content')
<x-page-header title="Performance Improvement Plan">
    <a href="{{ route('pips.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        <div class="card p-6">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-lg font-bold text-slate-800">{{ $pip->title }}</h2>
                <span class="badge-{{ $pip->status === 'active' ? 'yellow' : ($pip->status === 'completed' ? 'green' : 'red') }}">{{ ucfirst($pip->status) }}</span>
            </div>
            <p class="text-sm text-slate-600 mb-4">{{ $pip->description }}</p>
            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                <div class="bg-slate-50 p-3 rounded-lg"><p class="text-slate-500 text-xs mb-1">Start Date</p><p class="font-semibold">{{ $pip->start_date->format('M d, Y') }}</p></div>
                <div class="bg-slate-50 p-3 rounded-lg"><p class="text-slate-500 text-xs mb-1">End Date</p><p class="font-semibold">{{ $pip->end_date->format('M d, Y') }}</p></div>
            </div>
            @if($pip->objectives)
            <h4 class="font-semibold text-slate-700 mb-2">Objectives</h4>

            {{-- Each objective carries its own thread of files, so a reply sits
                 against the thing it answers rather than in one undifferentiated
                 pile at the bottom of the plan. --}}
            <div class="space-y-3 mb-4">
                @foreach($pip->objectives as $i => $obj)
                    @php $files = $pip->attachments->where('objective_index', $i); @endphp
                    <div class="border border-slate-200 rounded-lg p-3" x-data="{ adding: false }">
                        <div class="flex items-start justify-between gap-3">
                            <p class="flex items-start gap-2 text-sm text-slate-700">
                                <i class="fas fa-circle text-blue-400 text-xs mt-1.5"></i>{{ $obj }}
                            </p>
                            <button type="button" @click="adding = !adding"
                                    class="text-xs text-blue-600 hover:underline shrink-0">
                                <i class="fas fa-paperclip"></i>
                                <span x-text="adding ? 'Cancel' : 'Attach'"></span>
                            </button>
                        </div>

                        @include('performance.pip.partials.files', ['files' => $files, 'pip' => $pip])

                        <form method="POST" action="{{ route('pips.attachments.store', $pip) }}"
                              enctype="multipart/form-data" x-show="adding" x-cloak
                              class="mt-3 grid grid-cols-1 sm:grid-cols-12 gap-2">
                            @csrf
                            <input type="hidden" name="objective_index" value="{{ $i }}">
                            <input type="file" name="file" required accept="{{ \App\Support\Uploads::accept() }}"
                                   class="form-input text-xs sm:col-span-5" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.webp,.zip">
                            <input type="text" name="note" class="form-input text-xs sm:col-span-5"
                                   placeholder="A note with this file (optional)">
                            <button class="btn-primary text-xs sm:col-span-2" data-loading-label="Uploading…">
                                <i class="fas fa-upload mr-1"></i> Send
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
            @endif

            {{-- Anything belonging to the plan as a whole: the HR manual sent at
                 the start, or the closing pack sent with the outcome. --}}
            @php $general = $pip->attachments->whereNull('objective_index'); @endphp
            <div class="border-t border-slate-100 pt-4">
                <h4 class="font-semibold text-slate-700 mb-1">Documents for the whole plan</h4>
                <p class="text-xs text-slate-500 mb-2">
                    Reference material, or everything sent together at the end.
                </p>

                @include('performance.pip.partials.files', ['files' => $general, 'pip' => $pip])

                <form method="POST" action="{{ route('pips.attachments.store', $pip) }}"
                      enctype="multipart/form-data" class="mt-3 grid grid-cols-1 sm:grid-cols-12 gap-2">
                    @csrf
                    <input type="file" name="file" required accept="{{ \App\Support\Uploads::accept() }}"
                           class="form-input text-xs sm:col-span-5" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.webp,.zip">
                    <input type="text" name="note" class="form-input text-xs sm:col-span-5"
                           placeholder="e.g. HR manual to work to">
                    <button class="btn-secondary text-xs sm:col-span-2" data-loading-label="Uploading…">
                        <i class="fas fa-upload mr-1"></i> Attach
                    </button>
                </form>
                <p class="text-xs text-slate-400 mt-2">{{ \App\Support\Uploads::hint() }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('pips.update', $pip) }}" enctype="multipart/form-data" class="card p-6 space-y-4">@csrf @method('PUT')
            <h3 class="font-semibold text-slate-800">Update PIP</h3>
            <div><label class="form-label">Status</label>
                <select name="status" class="form-select">
                    @foreach(['active','completed','extended','cancelled'] as $s)
                    <option value="{{ $s }}" {{ $pip->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="form-label">Outcome / Notes</label><textarea name="outcome" class="form-input" rows="3">{{ $pip->outcome }}</textarea></div>

            {{-- Closing a plan is usually one act: the outcome and the paperwork
                 that supports it go together, so they are sent together rather
                 than as two separate trips. --}}
            <div>
                <label class="form-label">Attach a document with this update <span class="text-slate-400 font-normal">(optional)</span></label>
                <input type="file" name="file" class="form-input text-sm" accept="{{ \App\Support\Uploads::accept() }}"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.webp,.zip">
                <p class="text-xs text-slate-400 mt-1">Filed against the whole plan · {{ \App\Support\Uploads::hint() }}</p>
            </div>

            <button type="submit" class="btn-primary" data-loading-label="Saving &amp; sending…"><i class="fas fa-save"></i> Update</button>
        </form>
    </div>
    <div class="card p-6">
        <div class="flex items-center gap-4 mb-4">
            <img src="{{ $pip->employee->avatar_url }}" class="w-14 h-14 rounded-xl object-cover">
            <div>
                <h3 class="font-semibold text-slate-800">{{ $pip->employee->full_name }}</h3>
                <p class="text-xs text-slate-500">{{ $pip->employee->designation?->title }}</p>
            </div>
        </div>
        <div class="text-sm space-y-2">
            <div class="flex justify-between"><span class="text-slate-500">Department</span><span>{{ $pip->employee->department?->name }}</span></div>
            @if($pip->cycle)<div class="flex justify-between"><span class="text-slate-500">Cycle</span><span>{{ $pip->cycle->name ?? $pip->cycle->year }}</span></div>@endif
        </div>
    </div>
</div>
@endsection
