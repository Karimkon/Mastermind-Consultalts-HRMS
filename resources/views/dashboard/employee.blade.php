@extends('layouts.app')
@section('title', 'My Dashboard')
@section('content')

@if(!$employee)
<div class="card p-8 text-center">
    <i class="fas fa-user-slash text-4xl text-slate-300 mb-3 block"></i>
    <p class="text-slate-500">Your employee profile has not been set up yet. Please contact HR.</p>
</div>
@else

{{-- Welcome --}}
<div class="mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <img src="{{ $employee->avatar_url }}" class="w-14 h-14 rounded-full ring-2 ring-blue-100 object-cover">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ $employee->first_name }} 👋</h1>
            <p class="text-sm text-slate-500">{{ $employee->designation?->title ?? 'Employee' }} · {{ $employee->department?->name ?? '' }} · {{ now()->format('l, F j, Y') }}</p>
        </div>
    </div>
    {{-- Clock in/out --}}
    <div class="flex items-center gap-3">
        @if(!$todayLog || !$todayLog->clock_in)
        <form method="POST" action="{{ route('attendance.clock-in') }}">
            @csrf
            <button class="btn-primary bg-green-600 hover:bg-green-700"><i class="fas fa-sign-in-alt"></i> Clock In</button>
        </form>
        @elseif($todayLog && $todayLog->clock_in && !$todayLog->clock_out)
        <div class="text-sm text-slate-500 mr-2">In: <span class="font-semibold text-green-600">{{ \Carbon\Carbon::parse($todayLog->clock_in)->format('H:i') }}</span></div>
        <form method="POST" action="{{ route('attendance.clock-out') }}">
            @csrf
            <button class="btn-primary bg-red-600 hover:bg-red-700"><i class="fas fa-sign-out-alt"></i> Clock Out</button>
        </form>
        @else
        <div class="text-sm text-slate-500">
            <span class="text-green-600 font-semibold"><i class="fas fa-check-circle"></i> Done for today</span>
            <span class="ml-2 text-slate-400">{{ \Carbon\Carbon::parse($todayLog->clock_in)->format('H:i') }} – {{ \Carbon\Carbon::parse($todayLog->clock_out)->format('H:i') }}</span>
        </div>
        @endif
    </div>
</div>

{{-- Attendance Stats --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="card p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
            <i class="fas fa-check text-green-600"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wider">Present This Month</p>
            <p class="text-2xl font-bold text-slate-800">{{ $stats['present_this_month'] }}</p>
        </div>
    </div>
    <div class="card p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
            <i class="fas fa-times text-red-500"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wider">Absent This Month</p>
            <p class="text-2xl font-bold text-slate-800">{{ $stats['absent_this_month'] }}</p>
        </div>
    </div>
    <div class="card p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center">
            <i class="fas fa-clock text-yellow-600"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wider">Late This Month</p>
            <p class="text-2xl font-bold text-slate-800">{{ $stats['late_this_month'] }}</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Leave Balances --}}
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-800 text-sm">My Leave Balances</h2>
            <a href="{{ route('leaves.index') }}" class="text-xs text-blue-600 hover:underline">Apply for leave</a>
        </div>
        @forelse($leaveBalances as $bal)
        <div class="mb-3">
            <div class="flex justify-between text-sm mb-1">
                <span class="text-slate-700 font-medium">{{ $bal->leaveType?->name ?? 'Leave' }}</span>
                <span class="text-slate-500 text-xs">{{ $bal->used_days ?? 0 }} / {{ $bal->total_days ?? 0 }} used</span>
            </div>
            @php
                $total = $bal->total_days ?? 0;
                $used  = $bal->used_days ?? 0;
                $pct   = $total > 0 ? min(100, round($used / $total * 100)) : 0;
            @endphp
            <div class="h-2 bg-slate-100 rounded-full">
                <div class="h-2 rounded-full {{ $pct >= 80 ? 'bg-red-400' : 'bg-blue-500' }}" style="width:{{ $pct }}%"></div>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ max(0, $total - $used) }} days remaining</p>
        </div>
        @empty
        <p class="text-sm text-slate-400 text-center py-4">No leave balances allocated yet.</p>
        @endforelse
    </div>

    {{-- My Leave Requests --}}
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-800 text-sm">My Leave Requests</h2>
            <a href="{{ route('leaves.create') }}" class="text-xs text-blue-600 hover:underline">+ New request</a>
        </div>
        @forelse($leaves as $leave)
        <div class="flex items-start justify-between py-2 border-b border-slate-50 last:border-0">
            <div>
                <p class="text-sm font-medium text-slate-700">{{ $leave->leaveType?->name ?? 'Leave' }}</p>
                <p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($leave->from_date)->format('d M') }} – {{ \Carbon\Carbon::parse($leave->to_date)->format('d M Y') }}</p>
            </div>
            <span class="badge-{{ $leave->status === 'approved' ? 'green' : ($leave->status === 'rejected' ? 'red' : 'yellow') }} text-xs mt-0.5">
                {{ ucfirst($leave->status) }}
            </span>
        </div>
        @empty
        <p class="text-sm text-slate-400 text-center py-4">No leave requests yet.</p>
        @endforelse
    </div>

    {{-- My Payslips --}}
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-800 text-sm">My Payslips</h2>
        </div>
        @forelse($payslips as $slip)
        <div class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
            <div>
                <p class="text-sm font-medium text-slate-700">{{ $slip->payrollRun?->title ?? 'Payslip' }}</p>
                <p class="text-xs text-green-600 font-semibold">UGX {{ number_format($slip->net_salary, 0) }}</p>
            </div>
            @if($slip->payrollRun)
            <a href="{{ route('payroll.payslip.pdf', [$slip->payrollRun, $employee]) }}"
               class="text-xs text-red-500 hover:text-red-700 font-medium">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            @endif
        </div>
        @empty
        <p class="text-sm text-slate-400 text-center py-4">No payslips yet.</p>
        @endforelse
    </div>

</div>

{{-- Training & Meetings --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">

    {{-- My Trainings --}}
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-800 text-sm">My Training</h2>
            <a href="{{ route('training.index') }}" class="text-xs text-blue-600 hover:underline">Browse courses</a>
        </div>
        @forelse($enrollments as $enroll)
        <div class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-blue-500 text-xs"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-700">{{ $enroll->course?->title ?? 'Course' }}</p>
                    <p class="text-xs text-slate-400">{{ ucfirst($enroll->status) }}</p>
                </div>
            </div>
            <span class="badge-{{ $enroll->status === 'completed' ? 'green' : 'blue' }}">{{ ucfirst($enroll->status) }}</span>
        </div>
        @empty
        <p class="text-sm text-slate-400 text-center py-4">Not enrolled in any training.</p>
        @endforelse
    </div>

    {{-- My Meetings --}}
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-800 text-sm">Upcoming Meetings</h2>
            <a href="{{ route('meetings.calendar') }}" class="text-xs text-blue-600 hover:underline">View calendar</a>
        </div>
        @forelse($meetings as $meeting)
        <div class="flex items-start gap-3 py-2 border-b border-slate-50 last:border-0">
            <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center shrink-0 mt-0.5">
                <i class="fas fa-video text-purple-500 text-xs"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-700">{{ $meeting->title }}</p>
                <p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($meeting->start_at)->format('d M Y, H:i') }}</p>
            </div>
        </div>
        @empty
        <p class="text-sm text-slate-400 text-center py-4">No upcoming meetings.</p>
        @endforelse
    </div>

</div>

@endif
@endsection
