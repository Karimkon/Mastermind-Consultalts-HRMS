@extends("layouts.app")
@section("title", $activeClient ? $activeClient->company_name . ' — Leaves' : 'Leave Management')
@section("content")

<x-page-header
    :title="$activeClient ? $activeClient->company_name . ' — Leaves' : 'Leave Management'"
    subtitle="Approve or reject leave requests for your employees">
    <a href="{{ route('account-manager.dashboard') }}" class="btn-secondary text-sm">
        <i class="fas fa-arrow-left mr-1"></i> Dashboard
    </a>
</x-page-header>
<x-alert/>

{{-- Company filter tabs --}}
<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('account-manager.leaves') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium border transition
              {{ !$clientId ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
        <i class="fas fa-globe-africa mr-1"></i>All Companies
    </a>
    @foreach($clients as $c)
    <a href="{{ route('account-manager.leaves', ['client_id' => $c->id]) }}"
       class="px-4 py-2 rounded-lg text-sm font-medium border transition flex items-center gap-2
              {{ $clientId === $c->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
        <span class="w-2 h-2 rounded-full {{ $c->status === 'active' ? 'bg-emerald-400' : 'bg-slate-300' }}"></span>
        {{ $c->company_name }}
    </a>
    @endforeach
</div>

{{-- Status filter --}}
<div class="flex flex-wrap gap-2 mb-5">
    @foreach(['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $v => $l)
    <a href="{{ route('account-manager.leaves', array_filter(['client_id' => $clientId, 'status' => $v ?: null])) }}"
       class="btn-xs {{ request('status') == $v ? 'btn-blue' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
        {{ $l }}
    </a>
    @endforeach
</div>

<x-data-table>
    <thead class="bg-slate-50">
        <tr>
            <th class="table-head px-6 py-3 text-left">Employee</th>
            @if(!$clientId)<th class="table-head px-4 py-3 text-left">Company</th>@endif
            <th class="table-head px-4 py-3 text-left">Leave Type</th>
            <th class="table-head px-4 py-3 text-left">Period</th>
            <th class="table-head px-4 py-3 text-center">Days</th>
            <th class="table-head px-4 py-3 text-left">Replacement</th>
            <th class="table-head px-4 py-3 text-left">Status</th>
            <th class="table-head px-4 py-3 text-left">Actions</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($leaves as $leave)
        @php
            $company = $clients->first(fn($c) => $c->employees->contains('id', $leave->employee_id));
        @endphp
        <tr class="table-row">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <img src="{{ $leave->employee->avatar_url }}" class="w-8 h-8 rounded-full object-cover">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $leave->employee->full_name }}</p>
                        <p class="text-xs text-slate-400">{{ $leave->employee->department?->name }}</p>
                    </div>
                </div>
            </td>
            @if(!$clientId)
            <td class="px-4 py-3">
                @if($company)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700">
                    <i class="fas fa-building text-xs"></i> {{ $company->company_name }}
                </span>
                @else —
                @endif
            </td>
            @endif
            <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->leaveType?->name }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">
                {{ $leave->from_date?->format('d M Y') }} → {{ $leave->to_date?->format('d M Y') }}
            </td>
            <td class="px-4 py-3 text-center text-sm font-medium text-slate-700">
                {{ $leave->days_count ?? '—' }}
            </td>
            <td class="px-4 py-3 text-xs text-slate-600">
                @if($leave->replacement_name)
                <p class="font-medium text-slate-700">{{ $leave->replacement_name }}</p>
                @if($leave->replacement_phone)<p>{{ $leave->replacement_phone }}</p>@endif
                @else
                <span class="text-slate-300">—</span>
                @endif
            </td>
            <td class="px-4 py-3">
                {!! $leave->status_badge !!}
            </td>
            <td class="px-4 py-3">
                @if($leave->status === 'pending')
                <div class="flex gap-1">
                    <form method="POST" action="{{ route('account-manager.leaves.approve', $leave) }}">
                        @csrf
                        <button class="btn-xs btn-green"><i class="fas fa-check mr-1"></i>Approve</button>
                    </form>
                    <form method="POST" action="{{ route('account-manager.leaves.reject', $leave) }}">
                        @csrf
                        <input type="hidden" name="reason" value="Rejected by Account Manager">
                        <button class="btn-xs bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 rounded-md px-2 py-1 text-xs">
                            <i class="fas fa-times mr-1"></i>Reject
                        </button>
                    </form>
                </div>
                @else
                <span class="text-xs text-slate-400">—</span>
                @endif
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="{{ $clientId ? 7 : 8 }}" class="py-14 text-center text-slate-400">
                <i class="fas fa-calendar-check text-3xl opacity-30 block mb-2"></i>
                No leave requests found{{ $activeClient ? ' for ' . $activeClient->company_name : '' }}.
            </td>
        </tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $leaves->withQueryString()->links() }}</div>
@endsection
