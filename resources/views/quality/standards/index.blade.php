@extends('layouts.app')
@section('title', 'Quality Standards')
@section('content')

@php($sevColor = ['critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'slate'])
@php($functions = ['employee_central'=>'Employee Central','payroll'=>'Payroll','recruitment'=>'Recruitment','attendance'=>'Attendance','leave'=>'Leave','performance'=>'Performance','compliance'=>'Compliance'])
@php($categories = ['data_quality'=>'Data quality','compliance'=>'Compliance','process'=>'Process','accuracy'=>'Accuracy'])

<x-page-header title="Quality Standards" subtitle="The expectations every function is measured against - edit weights and severity to tune scoring">
    <a href="{{ route('quality.checks.index') }}" class="btn-secondary"><i class="fas fa-list-check mr-1"></i> Checks</a>
    <button onclick="document.getElementById('std-new').classList.toggle('hidden')" class="btn-primary"><i class="fas fa-plus mr-1"></i> Add standard</button>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div id="std-new" class="hidden mb-6 rounded-xl bg-white border border-slate-200 p-5">
    <form action="{{ route('quality.standards.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @csrf
        <div class="md:col-span-2"><label class="form-label">Title</label><input name="title" required class="form-input"></div>
        <div class="md:col-span-2"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-input"></textarea></div>
        <div><label class="form-label">Function</label><select name="hr_function" class="form-select">@foreach($functions as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div><label class="form-label">Category</label><select name="category" class="form-select">@foreach($categories as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div><label class="form-label">Severity</label><select name="severity" class="form-select"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
        <div><label class="form-label">Weight (1-100)</label><input type="number" name="weight" value="10" min="1" max="100" class="form-input"></div>
        <div><label class="form-label">Target score %</label><input type="number" name="target_score" value="100" min="1" max="100" class="form-input"></div>
        <div class="md:col-span-2 flex justify-end"><button class="btn-primary">Add standard</button></div>
    </form>
</div>

@foreach($standards as $function => $group)
<div class="mb-6">
    <h3 class="font-semibold text-slate-700 mb-2">{{ $functions[$function] ?? ucwords(str_replace('_',' ', $function)) }}</h3>
    <div class="rounded-xl bg-white border border-slate-200 divide-y divide-slate-100">
        @foreach($group as $std)
        <div x-data="{ edit: false }" class="px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="font-mono text-xs text-slate-400 w-20">{{ $std->code }}</span>
                <div class="flex-1">
                    <p class="font-medium text-slate-800 {{ $std->is_active ? '' : 'line-through text-slate-400' }}">{{ $std->title }}</p>
                    <p class="text-xs text-slate-400">{{ $std->description }}</p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $sevColor[$std->severity] ?? 'slate' }}-100 text-{{ $sevColor[$std->severity] ?? 'slate' }}-700">{{ $std->severity }}</span>
                <span class="text-xs text-slate-500 w-16 text-center">wt {{ $std->weight }}</span>
                <span class="text-xs text-slate-400 w-14 text-center">{{ $std->checks_count }} chk</span>
                <button @click="edit = !edit" class="text-slate-400 hover:text-slate-700"><i class="fas fa-pen text-xs"></i></button>
                <form action="{{ route('quality.standards.toggle', $std) }}" method="POST" class="inline">@csrf
                    <button class="text-xs {{ $std->is_active ? 'text-amber-600' : 'text-emerald-600' }}">{{ $std->is_active ? 'Disable' : 'Enable' }}</button>
                </form>
            </div>
            <div x-show="edit" x-cloak class="mt-3 pt-3 border-t border-slate-100">
                <form action="{{ route('quality.standards.update', $std) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    @csrf @method('PUT')
                    <div class="md:col-span-2"><label class="form-label">Title</label><input name="title" value="{{ $std->title }}" class="form-input"></div>
                    <div><label class="form-label">Severity</label><select name="severity" class="form-select">@foreach(['low','medium','high','critical'] as $s)<option value="{{ $s }}" {{ $std->severity === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach</select></div>
                    <div><label class="form-label">Weight</label><input type="number" name="weight" value="{{ $std->weight }}" min="1" max="100" class="form-input"></div>
                    <div class="md:col-span-4"><label class="form-label">Description</label><input name="description" value="{{ $std->description }}" class="form-input"></div>
                    <input type="hidden" name="hr_function" value="{{ $std->hr_function }}">
                    <input type="hidden" name="category" value="{{ $std->category }}">
                    <input type="hidden" name="target_score" value="{{ $std->target_score }}">
                    <div class="md:col-span-4 flex justify-end"><button class="btn-primary">Save</button></div>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endforeach

@endsection
