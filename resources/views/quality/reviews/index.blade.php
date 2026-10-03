@extends('layouts.app')
@section('title', 'Quality Reviews')
@section('content')

@php($statColor = ['draft' => 'amber', 'completed' => 'emerald'])

<x-page-header title="Quality Reviews" subtitle="Periodic management review of quality across the HRMS">
    <button onclick="document.getElementById('qr-new').classList.toggle('hidden')" class="btn-primary"><i class="fas fa-plus mr-1"></i> New review</button>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div id="qr-new" class="hidden mb-6 rounded-xl bg-white border border-slate-200 p-5">
    <form action="{{ route('quality.reviews.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @csrf
        <div class="md:col-span-2"><label class="form-label">Title</label><input name="title" required class="form-input" placeholder="e.g. Q3 2026 Quality Management Review"></div>
        <div><label class="form-label">Period start</label><input type="date" name="period_start" class="form-input"></div>
        <div><label class="form-label">Period end</label><input type="date" name="period_end" class="form-input"></div>
        <div><label class="form-label">Held on</label><input type="date" name="held_on" class="form-input"></div>
        <div class="md:col-span-2 flex justify-end"><button class="btn-primary">Open review</button></div>
    </form>
</div>

<x-data-table>
    <thead><tr class="table-header"><th>Ref</th><th>Review</th><th>Period</th><th>Chair</th><th>Score</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($reviews as $r)
    <tr class="table-row cursor-pointer" onclick="window.location='{{ route('quality.reviews.show', $r) }}'">
        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $r->reference }}</td>
        <td class="px-4 py-3 font-medium text-slate-800">{{ $r->title }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $r->period_start?->format('d M') }} - {{ $r->period_end?->format('d M Y') }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $r->chair?->name ?? '-' }}</td>
        <td class="px-4 py-3 text-sm font-semibold text-slate-700">{{ $r->overall_score !== null ? rtrim(rtrim(number_format($r->overall_score,1),'0'),'.').'%' : '-' }}</td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$r->status] ?? 'slate' }}-100 text-{{ $statColor[$r->status] ?? 'slate' }}-700">{{ $r->status }}</span></td>
    </tr>
    @empty
    <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">No reviews yet.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

<div class="mt-4">{{ $reviews->links() }}</div>

@endsection
