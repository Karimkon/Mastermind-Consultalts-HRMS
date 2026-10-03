@extends('layouts.app')
@section('title', 'Document Control')
@section('content')

@php($cats = \App\Models\QualityDocument::CATEGORIES)
@php($statColor = ['draft'=>'slate','in_review'=>'amber','pending_approval'=>'blue','approved'=>'emerald','published'=>'emerald','rejected'=>'red','archived'=>'slate'])

<x-page-header title="Document Control" subtitle="{{ $canManage ? 'Initiate and steer documents through edit, approval and publishing' : 'The controlled document register, open to you for review' }}">
    <a href="{{ route('quality.documents.library') }}" class="btn-secondary"><i class="fas fa-book mr-1"></i> Library</a>
    @if($canManage)<a href="{{ route('quality.documents.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New document</a>@endif
</x-page-header>

@if(session('success'))<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif

<x-data-table>
    <thead><tr class="table-header"><th>No.</th><th>Title</th><th>Category</th><th>Editor</th><th>Approver</th><th>Status</th><th>Review by</th></tr></thead>
    <tbody>
    @forelse($documents as $doc)
    <tr class="table-row cursor-pointer" onclick="window.location='{{ route('quality.documents.show', $doc) }}'">
        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $doc->doc_number }}</td>
        <td class="px-4 py-3 font-medium text-slate-800">{{ $doc->title }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $cats[$doc->category] ?? $doc->category }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $doc->editor?->name ?? '-' }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $doc->approver?->name ?? '-' }}</td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$doc->status] ?? 'slate' }}-100 text-{{ $statColor[$doc->status] ?? 'slate' }}-700">{{ str_replace('_',' ', $doc->status) }}</span></td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $doc->end_date?->format('d M Y') ?? '-' }}</td>
    </tr>
    @empty
    <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">No documents yet. Initiate your first one.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

<div class="mt-4">{{ $documents->links() }}</div>

@endsection
