@extends('layouts.app')
@section('title', 'Client Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Clients</h1>
        <p class="text-slate-500 text-sm">Manage client accounts and their portal access</p>
    </div>
    <div class="flex items-center gap-2" x-data="{ showImport: false }">
        {{-- Export --}}
        <a href="{{ route('admin.clients.export') }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border border-slate-200 bg-white text-slate-700 hover:bg-slate-50">
            <i class="fas fa-file-excel text-green-600"></i> Export Clients
        </a>
        {{-- Import trigger --}}
        <button @click="showImport = !showImport"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border border-slate-200 bg-white text-slate-700 hover:bg-slate-50">
            <i class="fas fa-file-upload text-indigo-600"></i> Import Clients
        </button>
        <a href="{{ route('admin.clients.create') }}" class="btn-primary"><i class="fas fa-plus mr-2"></i>Add Client</a>

        {{-- Import panel --}}
        <div x-show="showImport" @click.away="showImport = false" x-cloak
             class="absolute right-8 mt-24 w-80 bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-4">
            <p class="text-sm font-semibold text-slate-700 mb-2">Import Clients from Excel</p>
            <p class="text-xs text-slate-500 mb-3">
                Use the same columns as the export file. Existing clients (matched by company name) will be updated.
                New clients will be created with password <code class="bg-slate-100 px-1 rounded">Client@1234</code>.
            </p>
            <form method="POST" action="{{ route('admin.clients.import') }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".xlsx,.xls,.csv"
                       class="w-full text-xs border border-slate-200 rounded-lg p-2 mb-3" required>
                <div class="flex gap-2">
                    <button type="button" @click="showImport=false"
                            class="flex-1 px-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700">
                        <i class="fas fa-upload mr-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if(session('success'))
<div class="mb-4 flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif

<div class="card overflow-hidden">
    <table class="w-full">
        <thead class="table-header">
            <tr>
                <th>Company</th>
                <th>Contact Person</th>
                <th>Login Email</th>
                <th>Industry</th>
                <th>Employees</th>
                <th>Jobs</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($clients as $client)
            <tr class="table-row">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center font-bold text-blue-700 text-sm shrink-0">
                            {{ strtoupper(substr($client->company_name, 0, 2)) }}
                        </div>
                        <span class="text-sm font-medium text-slate-800">{{ $client->company_name }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ $client->contact_person }}</td>
                <td class="px-4 py-3 text-sm text-slate-500">{{ $client->user->email }}</td>
                <td class="px-4 py-3 text-sm text-slate-500">{{ $client->industry ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                    <span class="badge-blue">{{ $client->employees_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="badge-purple">{{ $client->job_postings_count }}</span>
                </td>
                <td class="px-4 py-3">{!! $client->status_badge !!}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.clients.show', $client) }}" class="btn-xs btn-blue">Manage</a>
                        <a href="{{ route('admin.clients.edit', $client) }}" class="btn-xs btn-amber">Edit</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                    <i class="fas fa-building text-4xl mb-3 block"></i>
                    No clients yet. <a href="{{ route('admin.clients.create') }}" class="text-blue-500 hover:underline">Add the first client</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $clients->links() }}</div>
@endsection
