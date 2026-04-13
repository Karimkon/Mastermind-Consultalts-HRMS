@extends('layouts.app')
@section('title','Goals')
@section('content')
<x-page-header title="Employee Goals" subtitle="Set, track and manage performance goals">
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <x-data-table>
            <thead class="bg-slate-50"><tr>
                <th class="table-head px-6 py-3 text-left">Goal</th>
                <th class="table-head px-4 py-3 text-left">Employee</th>
                <th class="table-head px-4 py-3 text-left">Progress</th>
                <th class="table-head px-4 py-3 text-left">Status</th>
                <th class="table-head px-4 py-3 text-left">Target</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($goals as $goal)
            <tr class="table-row" x-data="{ open: false }">
                <td class="px-6 py-3">
                    <p class="text-sm font-medium text-slate-800 cursor-pointer" @click="open=!open">{{ $goal->title }}</p>
                    <div x-show="open" x-cloak class="mt-2">
                        <p class="text-xs text-slate-500 mb-2">{{ $goal->description }}</p>
                        <form method="POST" action="{{ route('goals.progress', $goal) }}" class="flex gap-2 items-center">@csrf @method('PUT')
                            <input type="range" name="progress" min="0" max="100" value="{{ $goal->progress }}" class="flex-1" oninput="this.nextElementSibling.textContent=this.value+'%'">
                            <span class="text-xs font-mono w-10 text-slate-600">{{ $goal->progress }}%</span>
                            <button class="btn-primary text-xs py-1 px-2">Update</button>
                        </form>
                        <div class="flex gap-2 mt-2">
                            <form method="POST" action="{{ route('goals.destroy', $goal) }}" onsubmit="return confirm('Delete this goal?')">@csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3"><p class="text-sm text-slate-700">{{ $goal->employee->full_name }}</p></td>
                <td class="px-4 py-3">
                    <div class="w-24 bg-slate-100 rounded-full h-2 mb-1">
                        <div class="h-2 rounded-full {{ $goal->progress >= 100 ? 'bg-green-500' : 'bg-blue-500' }}" style="width:{{ $goal->progress }}%"></div>
                    </div>
                    <p class="text-xs text-slate-400">{{ $goal->progress }}%</p>
                </td>
                <td class="px-4 py-3">
                    <span class="badge-{{ $goal->status === 'achieved' ? 'green' : ($goal->status === 'in_progress' ? 'blue' : ($goal->status === 'missed' ? 'red' : 'slate')) }}">
                        {{ ucwords(str_replace('_',' ',$goal->status)) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-500">{{ $goal->target_date?->format('M d, Y') ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="py-12 text-center text-slate-400">No goals yet.</td></tr>
            @endforelse
            </tbody>
        </x-data-table>
        <div class="mt-4">{{ $goals->links() }}</div>
    </div>

    {{-- Add Goal --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Add Goal</h3>
        <form method="POST" action="{{ route('goals.store') }}" class="space-y-3">@csrf
            <div><label class="form-label">Employee *</label>
                <select name="employee_id" class="form-select select2" required>
                    <option value="">Select employee</option>
                    @foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->full_name }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Cycle</label>
                <select name="cycle_id" class="form-select">
                    <option value="">No cycle</option>
                    @foreach($cycles as $c)<option value="{{ $c->id }}">{{ $c->name ?? $c->year }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Goal Title *</label><input type="text" name="title" class="form-input" required></div>
            <div><label class="form-label">Description</label><textarea name="description" class="form-input" rows="2"></textarea></div>
            <div><label class="form-label">Target Date</label><input type="date" name="target_date" class="form-input"></div>
            <div><label class="form-label">Weight (%)</label><input type="number" name="weight" class="form-input" min="0" max="100" value="0"></div>
            <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-plus"></i> Add Goal</button>
        </form>
    </div>
</div>
@endsection
