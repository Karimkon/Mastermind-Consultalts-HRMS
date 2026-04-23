@extends("layouts.app")
@section("title", "Account Manager Dashboard")
@section("content")
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Account Manager Dashboard</h1>
    <p class="text-slate-500 text-sm mt-1">Managing {{ $clients->count() }} {{ Str::plural("company", $clients->count()) }} — {{ now()->format("l, d F Y") }}</p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center"><i class="fas fa-users text-blue-600 text-xl"></i></div>
        <div><p class="text-sm text-slate-500">Total Employees</p><p class="text-2xl font-bold text-slate-800">{{ $totalEmp }}</p></div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center"><i class="fas fa-check-circle text-green-600 text-xl"></i></div>
        <div><p class="text-sm text-slate-500">Present Today</p><p class="text-2xl font-bold text-slate-800">{{ $todayPresent }}</p></div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center"><i class="fas fa-calendar-minus text-yellow-600 text-xl"></i></div>
        <div><p class="text-sm text-slate-500">Pending Leaves</p><p class="text-2xl font-bold text-slate-800">{{ $pendingLeaves }}</p></div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center"><i class="fas fa-user-clock text-orange-600 text-xl"></i></div>
        <div><p class="text-sm text-slate-500">On Leave</p><p class="text-2xl font-bold text-slate-800">{{ $onLeave }}</p></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    @foreach($clients as $client)
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-semibold text-slate-800">{{ $client->company_name }}</h3>
                <p class="text-sm text-slate-400">{{ $client->industry }}</p>
            </div>
            <span class="badge-{{ $client->status === "active" ? "green" : "gray" }}">{{ ucfirst($client->status) }}</span>
        </div>
        <p class="text-3xl font-bold text-blue-600 mb-1">{{ $client->employees->count() }}</p>
        <p class="text-sm text-slate-500 mb-4">assigned employees</p>
        <div class="flex gap-2">
            <a href="{{ route("account-manager.employees") }}" class="btn-secondary text-sm"><i class="fas fa-users mr-1"></i>View Employees</a>
            <a href="{{ route("account-manager.leaves") }}" class="btn-secondary text-sm"><i class="fas fa-calendar mr-1"></i>Leaves</a>
        </div>
    </div>
    @endforeach
</div>
@endsection
