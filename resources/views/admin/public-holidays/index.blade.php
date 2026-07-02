@extends('layouts.app')
@section('title', 'Public Holidays Calendar')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Public Holidays Calendar</h1>
        <p class="text-slate-500 text-sm">Uganda public holidays used for payroll pro-rata calculations · 2024–2035</p>
    </div>
    <div class="flex items-center gap-2">
        {{-- Year filter --}}
        <form method="GET" class="flex items-center gap-2">
            <select name="year" onchange="this.form.submit()"
                    class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
                @foreach($years as $y)
                <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </form>
        <button type="button" onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="btn-primary"><i class="fas fa-plus mr-2"></i>Add Holiday</button>
    </div>
</div>

@if(session('success'))
<div class="mb-4 flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif

{{-- Info banner --}}
<div class="mb-5 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800 flex gap-3">
    <i class="fas fa-info-circle text-blue-500 mt-0.5 flex-shrink-0"></i>
    <div>
        <p class="font-semibold">How this works with Payroll</p>
        <p class="mt-1">Monthly staff are paid using: <strong>Annual Salary ÷ 365 × days_worked</strong>.
        Public holidays are <strong>paid by default</strong> — when payroll is processed using attendance logs,
        these holiday days are automatically added to each employee's worked days so they get full pay.
        When you upload a manual days file, public holidays should already be included in your count.</p>
    </div>
</div>

{{-- Stats row --}}
@php
    $byMonth = $holidays->groupBy(fn($h) => \Carbon\Carbon::parse($h->date)->format('F'));
@endphp
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase mb-1">Total Holidays {{ $year }}</p>
        <p class="text-3xl font-bold text-slate-800">{{ $holidays->count() }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase mb-1">Paid Holidays</p>
        <p class="text-3xl font-bold text-emerald-600">{{ $holidays->where('is_paid', true)->count() }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase mb-1">National Days</p>
        <p class="text-3xl font-bold text-blue-600">{{ $holidays->where('type', 'national')->count() }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase mb-1">Religious Days</p>
        <p class="text-3xl font-bold text-purple-600">{{ $holidays->where('type', 'religious')->count() }}</p>
    </div>
</div>

{{-- Holidays table --}}
<div class="card overflow-hidden">
    <table class="w-full">
        <thead class="table-header">
            <tr>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Holiday Name</th>
                <th class="px-4 py-3 text-left">Day</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-center">Paid</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($holidays as $h)
            <tr class="table-row">
                <td class="px-4 py-3">
                    <span class="font-semibold text-slate-800 text-sm">{{ \Carbon\Carbon::parse($h->date)->format('d M Y') }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ $h->name }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ \Carbon\Carbon::parse($h->date)->format('l') }}</td>
                <td class="px-4 py-3">
                    @if($h->type === 'national')
                    <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">
                        <i class="fas fa-flag text-xs"></i> National
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-medium">
                        <i class="fas fa-moon text-xs"></i> Religious
                    </span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    @if($h->is_paid)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Paid</span>
                    @else
                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">Unpaid</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <form method="POST" action="{{ route('admin.public-holidays.destroy', $h) }}"
                          onsubmit="return confirm('Delete {{ $h->name }}?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-600 hover:text-red-800 font-medium">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="py-12 text-center text-slate-400">
                    <i class="fas fa-calendar-times text-4xl mb-3 block"></i>
                    No public holidays for {{ $year }}.
                    <a href="#" onclick="document.getElementById('addModal').classList.remove('hidden'); return false;"
                       class="text-blue-500 hover:underline">Add one</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Add Holiday Modal --}}
<div id="addModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 mx-4">
        <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-calendar-plus text-blue-500"></i> Add Public Holiday
        </h3>
        <form method="POST" action="{{ route('admin.public-holidays.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="date" required
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Holiday Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. Independence Day"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                        <select name="type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="national">National</option>
                            <option value="religious">Religious</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Paid?</label>
                        <select name="is_paid" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="1">Yes (Paid)</option>
                            <option value="0">No (Unpaid)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    <i class="fas fa-save mr-1"></i> Save Holiday
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
