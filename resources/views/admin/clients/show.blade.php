@extends('layouts.app')
@section('title', $client->company_name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.clients.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $client->company_name }}</h1>
            <p class="text-slate-500 text-sm">{{ $client->contact_person }} · {{ $client->user->email }}</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        {!! $client->status_badge !!}
        <a href="{{ route('admin.clients.edit', $client) }}" class="btn-secondary"><i class="fas fa-edit mr-2"></i>Edit</a>
    </div>
</div>

<div x-data="{ tab: 'employees' }" class="space-y-4">
    {{-- Tabs --}}
    <div class="card p-1 flex gap-1 w-fit">
        <button @click="tab='employees'" :class="tab==='employees' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-users mr-2"></i>Assigned Employees ({{ $client->employees->count() }})
        </button>
        <button @click="tab='jobs'" :class="tab==='jobs' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-briefcase mr-2"></i>Assigned Jobs ({{ $client->jobPostings->count() }})
        </button>
    </div>

    {{-- Employees Tab --}}
    <div x-show="tab==='employees'">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Current employees --}}
            <div class="lg:col-span-2 card overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-700">Assigned Employees</h3>
                </div>
                @if($client->employees->isEmpty())
                <p class="px-4 py-8 text-center text-slate-400 text-sm">No employees assigned yet.</p>
                @else
                <table class="w-full">
                    <thead class="table-header"><tr><th>Employee</th><th>Department</th><th>Assigned</th><th></th></tr></thead>
                    <tbody>
                        @foreach($client->employees as $emp)
                        <tr class="table-row">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <img src="{{ $emp->user->avatar_url }}" class="w-8 h-8 rounded-full object-cover">
                                    <div>
                                        <p class="text-sm font-medium text-slate-800">{{ $emp->full_name }}</p>
                                        <p class="text-xs text-slate-400">{{ $emp->emp_number }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $emp->department->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $emp->pivot->created_at?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.clients.unassign-employee', [$client, $emp]) }}"
                                      onsubmit="return confirm('Remove {{ $emp->full_name }} from this client?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-xs" style="background:#fee2e2;color:#991b1b;">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>

            {{-- Assign employee form --}}
            <div class="card p-5">
                <h3 class="font-semibold text-slate-700 mb-3">Assign Employee</h3>
                <form method="POST" action="{{ route('admin.clients.assign-employee', $client) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <select name="employee_id" class="form-select select2" required>
                            <option value="">Select employee...</option>
                            @foreach($availableEmployees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="notes" rows="2" class="form-textarea" placeholder="Assignment notes..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center">Assign Employee</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Jobs Tab --}}
    <div x-show="tab==='jobs'">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Current jobs --}}
            <div class="lg:col-span-2 card overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-700">Assigned Job Postings</h3>
                </div>
                @if($client->jobPostings->isEmpty())
                <p class="px-4 py-8 text-center text-slate-400 text-sm">No job postings assigned yet.</p>
                @else
                <table class="w-full">
                    <thead class="table-header"><tr><th>Position</th><th>Department</th><th>Status</th><th>Assigned</th><th></th></tr></thead>
                    <tbody>
                        @foreach($client->jobPostings as $job)
                        <tr class="table-row">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-slate-800">{{ $job->title }}</p>
                                <p class="text-xs text-slate-400">{{ $job->reference_number }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $job->department->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="badge-{{ $job->status === 'open' ? 'green' : ($job->status === 'filled' ? 'blue' : 'gray') }}">{{ ucfirst($job->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $job->pivot->created_at?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.clients.unassign-job', [$client, $job]) }}"
                                      onsubmit="return confirm('Remove this job from client?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-xs" style="background:#fee2e2;color:#991b1b;">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>

            {{-- Assign job form --}}
            <div class="card p-5">
                <h3 class="font-semibold text-slate-700 mb-3">Assign Job Posting</h3>
                <form method="POST" action="{{ route('admin.clients.assign-job', $client) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Job Posting</label>
                        <select name="job_posting_id" class="form-select select2" required>
                            <option value="">Select job...</option>
                            @foreach($availableJobs as $job)
                            <option value="{{ $job->id }}">{{ $job->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="notes" rows="2" class="form-textarea" placeholder="Assignment notes..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center">Assign Job</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
