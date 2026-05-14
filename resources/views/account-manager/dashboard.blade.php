@extends("layouts.app")
@section("title", "Account Manager Dashboard")
@section("content")

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Account Manager Dashboard</h1>
        <p class="text-slate-500 text-sm mt-1">
            Managing <strong>{{ $clients->count() }}</strong> {{ Str::plural('company', $clients->count()) }} &nbsp;·&nbsp;
            {{ now()->format('l, d F Y') }}
        </p>
    </div>
</div>

{{-- Aggregate summary bar --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
            <i class="fas fa-users text-blue-600 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wide">Total Employees</p>
            <p class="text-2xl font-bold text-slate-800">{{ $totalEmp }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
            <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wide">Present Today</p>
            <p class="text-2xl font-bold text-slate-800">{{ $todayPresent }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center">
            <i class="fas fa-hourglass-half text-yellow-600 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wide">Pending Leaves</p>
            <p class="text-2xl font-bold text-slate-800">{{ $pendingLeaves }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center">
            <i class="fas fa-user-clock text-orange-600 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wide">On Leave</p>
            <p class="text-2xl font-bold text-slate-800">{{ $onLeave }}</p>
        </div>
    </div>
</div>

{{-- Per-client company cards --}}
<h2 class="text-lg font-semibold text-slate-700 mb-4">
    <i class="fas fa-building text-slate-400 mr-2"></i>Companies Under Management
</h2>

@if($clientStats->isEmpty())
    <div class="card p-10 text-center text-slate-400">
        <i class="fas fa-building text-4xl mb-3 block opacity-30"></i>
        No companies assigned to your account yet.
    </div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    @foreach($clientStats as $cs)
    @php $client = $cs['client']; @endphp
    <div class="card overflow-hidden border border-slate-200 hover:shadow-md transition-shadow">

        {{-- Card header with company colour stripe --}}
        <div class="h-2 bg-gradient-to-r from-blue-500 to-indigo-600"></div>

        <div class="p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-bold text-slate-800 text-lg leading-tight">{{ $client->company_name }}</h3>
                    @if($client->industry)
                    <p class="text-xs text-slate-400 mt-0.5">{{ $client->industry }}</p>
                    @endif
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $client->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ ucfirst($client->status) }}
                </span>
            </div>

            {{-- Per-company stats --}}
            <div class="grid grid-cols-4 gap-2 mb-4 text-center">
                <div class="bg-slate-50 rounded-lg p-2">
                    <p class="text-xl font-bold text-slate-800">{{ $cs['total'] }}</p>
                    <p class="text-xs text-slate-400">Staff</p>
                </div>
                <div class="bg-emerald-50 rounded-lg p-2">
                    <p class="text-xl font-bold text-emerald-700">{{ $cs['present_today'] }}</p>
                    <p class="text-xs text-slate-400">In Today</p>
                </div>
                <div class="bg-yellow-50 rounded-lg p-2">
                    <p class="text-xl font-bold text-yellow-700">{{ $cs['pending_leaves'] }}</p>
                    <p class="text-xs text-slate-400">Pending</p>
                </div>
                <div class="bg-orange-50 rounded-lg p-2">
                    <p class="text-xl font-bold text-orange-700">{{ $cs['on_leave'] }}</p>
                    <p class="text-xs text-slate-400">On Leave</p>
                </div>
            </div>

            @if($client->work_site_address)
            <p class="text-xs text-slate-500 mb-3 flex items-center gap-1">
                <i class="fas fa-map-marker-alt text-slate-400"></i>
                {{ $client->work_site_address }}
                @if($client->geo_fence_radius)
                <span class="ml-1 bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded text-xs">{{ $client->geo_fence_radius }}m radius</span>
                @endif
            </p>
            @endif

            @if($client->payment_day)
            <p class="text-xs text-slate-500 mb-3 flex items-center gap-1">
                <i class="fas fa-calendar-check text-slate-400"></i>
                Pay day: <strong class="text-slate-700">{{ $client->payment_day }}{{ ['th','st','nd','rd'][$client->payment_day % 10 <= 3 && ($client->payment_day < 11 || $client->payment_day > 13) ? $client->payment_day % 10 : 0] }}</strong> of every month
            </p>
            @endif

            {{-- Action buttons scoped to THIS client --}}
            <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-100">
                <a href="{{ route('account-manager.employees', ['client_id' => $client->id]) }}"
                   class="flex-1 btn-secondary text-sm text-center">
                    <i class="fas fa-users mr-1"></i>Employees
                </a>
                <a href="{{ route('account-manager.leaves', ['client_id' => $client->id]) }}"
                   class="flex-1 btn-secondary text-sm text-center">
                    <i class="fas fa-calendar-minus mr-1"></i>Leaves
                    @if($cs['pending_leaves'] > 0)
                    <span class="ml-1 bg-red-500 text-white rounded-full px-1.5 text-xs">{{ $cs['pending_leaves'] }}</span>
                    @endif
                </a>
                @if(auth()->user()->hasAnyRole(['super-admin','hr-admin']) || $client->account_manager_id === auth()->id())
                <a href="{{ route('account-manager.client.settings', $client) }}"
                   class="btn-xs bg-slate-100 text-slate-600 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-sm">
                    <i class="fas fa-cog"></i>
                </a>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
