@extends('layouts.app')
@section('title','Review Change')
@section('content')

<x-page-header title="{{ $change->label ?? $change->model_type.' #'.$change->model_id }}"
    subtitle="Asked for by {{ $change->requester?->name ?? 'someone' }} on {{ $change->created_at->format('d M Y, H:i') }}">
    <a href="{{ route('admin.change-approvals.index') }}" class="btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to queue
    </a>
</x-page-header>

@if(! $change->isPending())
<div class="mb-5 flex items-start gap-3 px-4 py-3 bg-{{ $change->statusBadge() }}-50 border border-{{ $change->statusBadge() }}-200 rounded-lg text-{{ $change->statusBadge() }}-800 text-sm">
    <i class="fas fa-circle-info mt-0.5"></i>
    <span>
        <strong>{{ ucfirst($change->status) }}</strong>
        by {{ $change->reviewer?->name ?? 'someone' }}
        on {{ $change->reviewed_at?->format('d M Y, H:i') }}.
        @if($change->review_note)<span class="block mt-1 italic">&ldquo;{{ $change->review_note }}&rdquo;</span>@endif
    </span>
</div>
@endif

@if(! $subject)
<div class="mb-5 flex items-start gap-3 px-4 py-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm">
    <i class="fas fa-triangle-exclamation mt-0.5"></i>
    <span>The record this refers to no longer exists, so this change cannot be applied.</span>
</div>
@endif

@if($change->isPending() && $change->hasDrift())
{{-- Somebody edited the record after this was asked for. Approving would undo
     their edit, so it is said plainly rather than left to be discovered. --}}
<div class="mb-5 flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-sm">
    <i class="fas fa-triangle-exclamation mt-0.5"></i>
    <span>
        <strong>This record has changed since the request was made.</strong>
        The rows marked below now hold a different value from the one the account manager
        was looking at. Approving will overwrite what is there now.
    </span>
</div>
@endif

<div class="card p-5 mb-5">
    <h3 class="font-semibold text-slate-800 mb-1">{{ count($diff) }} field(s) would change</h3>
    <p class="text-xs text-slate-500 mb-4">Nothing below has been written yet.</p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-3 py-2 text-xs font-semibold text-slate-600">Field</th>
                    <th class="px-3 py-2 text-xs font-semibold text-slate-600">Now on file</th>
                    <th class="px-3 py-2 text-xs font-semibold text-slate-600">Would become</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($diff as $row)
                <tr class="{{ $row['drifted'] ? 'bg-amber-50/60' : '' }}">
                    <td class="px-3 py-2 font-mono text-xs text-slate-700">
                        {{ str_replace('_', ' ', $row['field']) }}
                        @if($row['drifted'])
                        <span class="block text-[11px] text-amber-700 font-sans mt-0.5">
                            changed since the request (was &ldquo;{{ $row['was'] ?: '—' }}&rdquo;)
                        </span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-slate-500">
                        {{ ($row['current'] === null || $row['current'] === '') ? '—' : $row['current'] }}
                    </td>
                    <td class="px-3 py-2 font-medium text-slate-900">
                        {{ ($row['proposed'] === null || $row['proposed'] === '') ? '—' : $row['proposed'] }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($change->isPending() && $subject)
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="card p-5 border-l-4 border-green-500">
        <h3 class="font-semibold text-slate-800 mb-2">Approve</h3>
        <p class="text-sm text-slate-600 mb-3">The values above are written to the record and the account manager is told.</p>
        <form method="POST" action="{{ route('admin.change-approvals.approve', $change) }}"
              onsubmit="return confirm('Apply these {{ count($diff) }} change(s)?')">
            @csrf
            <input type="text" name="review_note" class="form-input text-sm mb-3" placeholder="Note (optional)">
            <button class="btn-primary"><i class="fas fa-check mr-1"></i> Approve and apply</button>
        </form>
    </div>

    <div class="card p-5 border-l-4 border-rose-400">
        <h3 class="font-semibold text-slate-800 mb-2">Reject</h3>
        <p class="text-sm text-slate-600 mb-3">The record is left alone. A reason is required so the account manager knows what to correct.</p>
        <form method="POST" action="{{ route('admin.change-approvals.reject', $change) }}">
            @csrf
            <input type="text" name="review_note" class="form-input text-sm mb-1" required
                   placeholder="Why is this being rejected?">
            @error('review_note')<p class="text-xs text-red-600 mb-2">{{ $message }}</p>@enderror
            <button class="btn-secondary text-rose-600 mt-2"><i class="fas fa-xmark mr-1"></i> Reject</button>
        </form>
    </div>
</div>
@endif
@endsection
