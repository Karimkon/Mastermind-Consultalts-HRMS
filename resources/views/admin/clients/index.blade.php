@extends('layouts.app')
@section('title', 'Client Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Clients</h1>
        <p class="text-slate-500 text-sm">Manage client accounts and their portal access</p>
    </div>
    <a href="{{ route('admin.clients.create') }}" class="btn-primary"><i class="fas fa-plus mr-2"></i>Add Client</a>
</div>

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
