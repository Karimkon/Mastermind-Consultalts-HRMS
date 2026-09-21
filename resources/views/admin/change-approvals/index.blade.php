@extends('layouts.app')
@section('title','Change Approvals')
@section('content')

<x-page-header title="Change Approvals"
    subtitle="Edits account managers have asked for. Nothing here has happened yet.">
    <a href="{{ route('admin.audit.index') }}" class="btn-secondary">
        <i class="fas fa-clipboard-list mr-1"></i> Audit Trail
    </a>
</x-page-header>

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

<div class="flex flex-wrap items-center gap-2 mb-5">
    @foreach(['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
    <a href="{{ route('admin.change-approvals.index', ['status' => $value]) }}"
       class="px-4 py-2 rounded-lg text-sm font-medium {{ $status === $value ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
        {{ $label }}
        @if($value === 'pending' && $pendingCount)
        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs {{ $status === $value ? 'bg-white/25' : 'bg-amber-100 text-amber-700' }}">{{ $pendingCount }}</span>
        @endif
    </a>
    @endforeach
</div>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">What</th>
        <th class="table-head px-4 py-3 text-left">Asked by</th>
        <th class="table-head px-4 py-3 text-left">Client</th>
        <th class="table-head px-4 py-3 text-center">Fields</th>
        <th class="table-head px-4 py-3 text-left">When</th>
        <th class="table-head px-4 py-3"></th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($changes as $change)
        <tr class="table-row">
            <td class="px-6 py-3">
                <p class="text-sm font-medium text-slate-800">{{ $change->label ?? $change->model_type.' #'.$change->model_id }}</p>
                <p class="text-xs text-slate-400">{{ ucfirst($change->action) }} &middot; {{ $change->model_type }}</p>
                @if($change->status !== 'pending' && $change->review_note)
                <p class="text-xs text-slate-500 mt-1 italic">&ldquo;{{ $change->review_note }}&rdquo;</p>
                @endif
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $change->requester?->name ?? '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $change->client?->company_name ?? '—' }}</td>
            <td class="px-4 py-3 text-center text-sm text-slate-700">{{ count($change->payload) }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">
                {{ $change->created_at->format('d M Y, H:i') }}
                @if($change->reviewed_at)
                <span class="block text-slate-400">
                    {{ ucfirst($change->status) }} by {{ $change->reviewer?->name ?? '—' }}
                </span>
                @endif
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
                <a href="{{ route('admin.change-approvals.show', $change) }}" class="text-blue-600 hover:underline text-xs">
                    {{ $change->isPending() ? 'Review' : 'View' }}
                </a>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" class="py-12 text-center">
            <i class="fas fa-circle-check text-3xl text-slate-300 mb-3 block"></i>
            <p class="text-slate-500 text-sm">
                {{ $status === 'pending' ? 'Nothing is waiting for approval.' : 'Nothing here.' }}
            </p>
        </td></tr>
        @endforelse
    </tbody>
</x-data-table>

<div class="mt-4">{{ $changes->links() }}</div>
@endsection
