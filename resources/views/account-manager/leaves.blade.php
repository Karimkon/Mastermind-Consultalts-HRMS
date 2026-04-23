@extends("layouts.app")
@section("title", "Leave Management")
@section("content")
<x-page-header title="Leave Management" subtitle="Approve or reject leave requests for your employees"/>
<x-alert/>

<div class="mb-4 flex gap-2">
    @foreach([''=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l)
    <a href="{{ route('account-manager.leaves', $v ? ['status'=>$v] : []) }}"
       class="btn-xs {{ request('status')==$v ? 'btn-blue' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $l }}</a>
    @endforeach
</div>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-left">Leave Type</th>
        <th class="table-head px-4 py-3 text-left">From</th>
        <th class="table-head px-4 py-3 text-left">To</th>
        <th class="table-head px-4 py-3 text-left">Days</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
        <th class="table-head px-4 py-3 text-left">Actions</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($leaves as $leave)
        <tr class="table-row">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <img src="{{ $leave->employee->avatar_url }}" class="w-8 h-8 rounded-full object-cover">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $leave->employee->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $leave->employee->department?->name }}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->leaveType?->name }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->start_date?->format('d M Y') }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->end_date?->format('d M Y') }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->days_requested ?? '—' }}</td>
            <td class="px-4 py-3">
                <span class="badge-{{ $leave->status==='approved'?'green':($leave->status==='rejected'?'red':'yellow') }}">{{ ucfirst($leave->status) }}</span>
            </td>
            <td class="px-4 py-3">
                @if($leave->status === 'pending')
                <div class="flex gap-1">
                    <form method="POST" action="{{ route('account-manager.leaves.approve', $leave) }}">
                        @csrf <button class="btn-xs btn-green"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <form method="POST" action="{{ route('account-manager.leaves.reject', $leave) }}">
                        @csrf <button class="btn-xs bg-red-50 text-red-600 hover:bg-red-100"><i class="fas fa-times"></i> Reject</button>
                    </form>
                </div>
                @else <span class="text-xs text-slate-400">—</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="py-12 text-center text-slate-400">No leave requests found.</td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $leaves->withQueryString()->links() }}</div>
@endsection