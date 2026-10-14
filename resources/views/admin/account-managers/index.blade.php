@extends('layouts.app')
@section('title', 'Account Managers')
@section('content')

<x-page-header title="Account Managers" subtitle="View and manage account managers and their client assignments">
    <a href="{{ route('admin.users.create') }}" class="btn-primary">
        <i class="fas fa-plus mr-2"></i>Add Account Manager
    </a>
</x-page-header>

@include('admin.account-managers._manage', [
    'managers' => $managers,
    'promotable' => $promotable,
    'allClients' => $allClients,
])

@if($unassigned->count())
<div class="card p-4 mb-6 border-l-4 border-yellow-400">
    <p class="text-sm text-yellow-700 font-medium">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        {{ $unassigned->count() }} client(s) have no Account Manager assigned:
        {{ $unassigned->pluck('company_name')->join(', ') }}
    </p>
</div>
@endif

{{-- AM Cards --}}
@forelse($managers as $manager)
<div class="card mb-6 overflow-hidden">
    {{-- AM Header --}}
    <div class="flex items-center gap-4 p-5 border-b border-slate-100 bg-slate-50">
        <img src="{{ $manager->avatar_url }}" class="w-12 h-12 rounded-full border-2 border-blue-200">
        <div class="flex-1">
            <p class="font-bold text-slate-800 text-base">{{ $manager->name }}</p>
            <p class="text-sm text-slate-500">{{ $manager->email }}</p>
        </div>
        <div class="flex gap-6 text-center">
            <div>
                <p class="text-xl font-black text-blue-600">{{ $manager->managedClients->count() }}</p>
                <p class="text-xs text-slate-500 font-medium">Clients</p>
            </div>
            <div>
                <p class="text-xl font-black text-green-600">{{ $manager->managedClients->sum('employees_count') }}</p>
                <p class="text-xs text-slate-500 font-medium">Employees</p>
            </div>
        </div>
        <a href="{{ route('account-manager.dashboard') }}" class="btn-secondary text-sm" target="_blank">
            <i class="fas fa-external-link-alt mr-1"></i>View AM Portal
        </a>
    </div>

    @if($manager->managedClients->isEmpty())
        <div class="p-6 text-center text-slate-400 text-sm">
            <i class="fas fa-building text-2xl mb-2 block opacity-30"></i>
            No clients assigned yet
        </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Client</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Employees</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($manager->managedClients as $client)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold">
                                {{ strtoupper(substr($client->company_name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-semibold text-slate-800">{{ $client->company_name }}</p>
                                @if($client->contact_person)<p class="text-xs text-slate-500">{{ $client->contact_person }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $client->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ ucfirst($client->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="font-bold text-slate-800">{{ $client->employees_count }}</span>
                        <span class="text-slate-400 text-xs ml-1">assigned</span>
                    </td>
                    <td class="px-5 py-3 text-slate-600 text-xs">
                        @if($client->email)<div><i class="fas fa-envelope mr-1 text-slate-400"></i>{{ $client->email }}</div>@endif
                        @if($client->phone)<div class="mt-0.5"><i class="fas fa-phone mr-1 text-slate-400"></i>{{ $client->phone }}</div>@endif
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex gap-2">
                            <a href="{{ route('admin.clients.show', $client) }}" class="btn-secondary text-xs py-1 px-3">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                            <a href="{{ route('account-manager.employees') }}?client_id={{ $client->id }}"
                               class="btn-secondary text-xs py-1 px-3">
                                <i class="fas fa-users mr-1"></i>Employees
                            </a>
                        </div>
                    </td>
                </tr>

                {{--
                    Employee preview.

                    This used to load and render EVERY employee of every client:
                    1,877 chips on one page, 1.6MB of HTML. Worse, avatar_url
                    falls back to ui-avatars.com for anybody without a login,
                    and most placed staff have none -- so the browser fired
                    close to 1,877 requests at a third party before the page
                    settled. That is what made this screen crawl.

                    A dozen is a preview; the full list already has its own page
                    and is linked below.
                --}}
                @php
                    $previewLimit = 12;
                    $clientEmployees = \App\Models\Employee::with(['department:id,name', 'user:id,name,avatar'])
                        ->whereHas('clients', fn($q) => $q->where('clients.id', $client->id))
                        ->orderBy('first_name')
                        ->limit($previewLimit)
                        ->get(['employees.id', 'first_name', 'middle_name', 'last_name', 'department_id', 'user_id']);
                    $totalEmployees = $client->employees_count ?? $clientEmployees->count();
                @endphp
                @if($clientEmployees->count())
                <tr class="bg-slate-50/50">
                    <td colspan="5" class="px-5 pb-3 pt-0">
                        <div class="mt-2">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
                                <i class="fas fa-users mr-1"></i>{{ number_format($totalEmployees) }} Assigned Employees
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($clientEmployees as $emp)
                                <a href="{{ route('account-manager.employees.show', $emp) }}"
                                   class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-blue-300 hover:bg-blue-50 transition-colors text-xs">
                                    <img src="{{ $emp->avatar_url }}" class="w-5 h-5 rounded-full"
                                         loading="lazy" decoding="async" width="20" height="20" alt="">
                                    <span class="font-medium text-slate-700">{{ $emp->full_name }}</span>
                                    <span class="text-slate-400">{{ $emp->department?->name ?? '—' }}</span>
                                </a>
                                @endforeach

                                @if($totalEmployees > $previewLimit)
                                <a href="{{ route('account-manager.employees') }}?client_id={{ $client->id }}"
                                   class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 hover:bg-blue-100 transition-colors text-xs font-medium">
                                    and {{ number_format($totalEmployees - $previewLimit) }} more
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@empty
<div class="card p-12 text-center">
    <i class="fas fa-user-tie text-5xl text-slate-300 mb-4 block"></i>
    <p class="text-slate-500 font-medium">No account managers found</p>
    <p class="text-slate-400 text-sm mt-1">Create a user and assign them the <strong>account-manager</strong> role to get started.</p>
    <a href="{{ route('admin.users.create') }}" class="btn-primary mt-4 inline-flex">
        <i class="fas fa-plus mr-2"></i>Create Account Manager
    </a>
</div>
@endforelse

@endsection
