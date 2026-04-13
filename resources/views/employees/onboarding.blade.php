@extends('layouts.app')
@section('title','Onboarding — ' . $employee->full_name)
@section('content')
<x-page-header title="Onboarding Checklist" :subtitle="$employee->full_name">
    <a href="{{ route('employees.show', $employee) }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Task List --}}
    <div class="lg:col-span-2 card overflow-hidden">
        @php $completed = $tasks->filter(fn($t) => $t->completed_at)->count(); @endphp
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Tasks <span class="text-slate-400 font-normal text-sm">({{ $completed }}/{{ $tasks->count() }} completed)</span></h3>
            <div class="w-32 bg-slate-100 rounded-full h-2">
                <div class="bg-green-500 h-2 rounded-full" style="width:{{ $tasks->count() > 0 ? round($completed/$tasks->count()*100) : 0 }}%"></div>
            </div>
        </div>
        @forelse($tasks as $task)
        <div class="flex items-start gap-4 px-6 py-4 border-b border-slate-50 last:border-0 {{ $task->completed_at ? 'bg-green-50/30' : '' }}">
            <form method="POST" action="{{ route('employees.onboarding.complete', $task) }}" class="mt-0.5">@csrf
                <button type="submit" class="w-5 h-5 rounded border-2 {{ $task->completed_at ? 'bg-green-500 border-green-500 text-white' : 'border-slate-300' }} flex items-center justify-center transition-colors">
                    @if($task->completed_at)<i class="fas fa-check text-xs"></i>@endif
                </button>
            </form>
            <div class="flex-1">
                <p class="text-sm font-medium text-slate-800 {{ $task->completed_at ? 'line-through text-slate-400' : '' }}">{{ $task->task }}</p>
                @if($task->description)<p class="text-xs text-slate-500 mt-0.5">{{ $task->description }}</p>@endif
                @if($task->completed_at)<p class="text-xs text-green-600 mt-1"><i class="fas fa-check-circle mr-1"></i>Completed {{ $task->completed_at->diffForHumans() }}</p>@endif
            </div>
            <form method="POST" action="{{ route('employees.onboarding.destroy', $task) }}" onsubmit="return confirm('Remove this task?')">@csrf @method('DELETE')
                <button type="submit" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fas fa-trash text-xs"></i></button>
            </form>
        </div>
        @empty
        <div class="py-12 text-center text-slate-400">No onboarding tasks yet. Add tasks using the form.</div>
        @endforelse
    </div>

    {{-- Add Task Form --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Add Task</h3>
        <form method="POST" action="{{ route('employees.onboarding.store', $employee) }}" class="space-y-3">@csrf
            <div><label class="form-label">Task *</label><input type="text" name="task" class="form-input" required placeholder="e.g. Complete employee agreement"></div>
            <div><label class="form-label">Description</label><textarea name="description" class="form-input" rows="2" placeholder="Optional details..."></textarea></div>
            <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-plus"></i> Add Task</button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-100">
            <h4 class="text-sm font-semibold text-slate-700 mb-3">Quick Add Templates</h4>
            <div class="space-y-1.5">
                @foreach(['Sign employment contract','Set up workstation','IT system access granted','HR induction session','Meet the team','Complete payroll forms','ID and document verification','Health & safety briefing'] as $t)
                <form method="POST" action="{{ route('employees.onboarding.store', $employee) }}">@csrf
                    <input type="hidden" name="task" value="{{ $t }}">
                    <button type="submit" class="text-xs text-left text-blue-600 hover:text-blue-800 w-full py-1 border-b border-slate-50">+ {{ $t }}</button>
                </form>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
