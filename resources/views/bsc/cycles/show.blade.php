@extends('layouts.app')
@section('title', $cycle->name)
@section('content')
<x-page-header title="{{ $cycle->name }}">
    <div class="flex items-center gap-2">
        {!! $cycle->status_badge !!}
        <span class="text-sm text-slate-500">{{ $cycle->period }} {{ $cycle->year }} &bull; {{ $cycle->start_date->format('d M') }} – {{ $cycle->end_date->format('d M Y') }}</span>
    </div>
    <div class="flex gap-2">
        @if($cycle->status === 'draft')
        <form method="POST" action="{{ route('bsc.cycles.activate', $cycle) }}" class="inline">
            @csrf
            <button class="btn-primary"><i class="fas fa-play mr-1"></i> Activate Cycle</button>
        </form>
        @elseif($cycle->status === 'active')
        <form method="POST" action="{{ route('bsc.cycles.close', $cycle) }}" class="inline"
              onsubmit="return confirm('Close this cycle?')">
            @csrf
            <button class="btn-secondary"><i class="fas fa-lock mr-1"></i> Close Cycle</button>
        </form>
        @endif
        <a href="{{ route('bsc.kras.create', $cycle) }}" class="btn-secondary"><i class="fas fa-plus mr-1"></i> Add KRA</a>
        <a href="{{ route('bsc.cycles.edit', $cycle) }}" class="btn-secondary"><i class="fas fa-edit mr-1"></i> Edit</a>
    </div>
</x-page-header>

@if(session('success'))
<div class="alert-success mb-4">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert-error mb-4">{{ session('error') }}</div>
@endif

{{-- Perspective Summary Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach($perspectives as $key => $p)
    @php
    $colorMap = ['blue'=>'blue','green'=>'green','purple'=>'purple','orange'=>'amber'];
    $c = $colorMap[$p['color']] ?? 'slate';
    @endphp
    <div class="card p-4 border-t-4 border-{{ $c }}-400">
        <p class="text-xs font-semibold text-{{ $c }}-600 uppercase tracking-wide mb-1">{{ $p['label'] }}</p>
        <p class="text-3xl font-black text-{{ $c }}-700">{{ $p['weight'] }}%</p>
        <div class="flex items-center justify-between mt-2 text-xs text-slate-500">
            <span>{{ $p['kra_count'] }} KRAs</span>
            <span>{{ number_format($p['total_weightage'], 1) }}% assigned</span>
        </div>
        @if($p['total_weightage'] > 0)
        <div class="mt-2 h-1.5 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full bg-{{ $c }}-400 rounded-full" style="width: {{ min(100, $p['total_weightage']) }}%"></div>
        </div>
        @endif
    </div>
    @endforeach
</div>

{{-- Initialize Appraisal --}}
@if($cycle->status === 'active')
<div class="card p-5 mb-6 bg-blue-50 border-blue-200">
    <div class="flex items-start justify-between">
        <div>
            <h4 class="font-bold text-blue-800 flex items-center gap-2"><i class="fas fa-rocket"></i> Initialize Employee Appraisals</h4>
            <p class="text-sm text-blue-600 mt-1">Create appraisal entry records for employees in this cycle.</p>
        </div>
        <button onclick="document.getElementById('initModal').classList.remove('hidden')" class="btn-primary text-sm">
            <i class="fas fa-users mr-1"></i> Initialize
        </button>
    </div>
</div>

{{-- Initialize Modal --}}
<div id="initModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="card p-6 w-full max-w-md">
        <h3 class="font-bold text-slate-800 mb-4">Initialize Appraisals</h3>
        <form method="POST" action="{{ route('bsc.cycles.initialize', $cycle) }}">
            @csrf
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Employees</label>
                <div class="flex gap-3 mb-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="all_employees" value="1" checked> All Active Employees
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="all_employees" value="0" id="selSpecific"> Select Specific
                    </label>
                </div>
                <div id="empSelect" class="hidden">
                    <select name="employee_ids[]" multiple class="form-input h-36">
                        @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Hold Ctrl/Cmd to select multiple</p>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Appraiser Role</label>
                <select name="appraiser_role" class="form-input" required>
                    <option value="account_manager">Account Manager</option>
                    <option value="hr">HR</option>
                    <option value="admin">Admin</option>
                    <option value="self">Self</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1 justify-center">Initialize</button>
                <button type="button" onclick="document.getElementById('initModal').classList.add('hidden')" class="btn-secondary flex-1 justify-center">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('input[name="all_employees"]').forEach(function(r){
    r.addEventListener('change', function(){
        document.getElementById('empSelect').classList.toggle('hidden', this.value === '1');
    });
});
</script>
@endif

{{-- KRAs by Perspective --}}
@foreach($perspectives as $key => $p)
@php $c = $colorMap[$p['color']] ?? 'slate'; @endphp
<div class="card mb-4">
    <div class="px-5 py-3 bg-{{ $c }}-50 border-b border-{{ $c }}-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-full bg-{{ $c }}-100 flex items-center justify-center">
                <i class="fas fa-circle text-{{ $c }}-500 text-xs"></i>
            </span>
            <div>
                <h4 class="font-bold text-{{ $c }}-800">{{ $p['label'] }}</h4>
                <p class="text-xs text-{{ $c }}-600">Weight: <strong>{{ $p['weight'] }}%</strong> of total score &bull; {{ $p['kra_count'] }} KRAs</p>
            </div>
        </div>
        <a href="{{ route('bsc.kras.create', ['cycle' => $cycle, 'perspective' => $key]) }}" class="text-xs text-{{ $c }}-600 hover:text-{{ $c }}-800 font-semibold">
            <i class="fas fa-plus mr-1"></i> Add KRA
        </a>
    </div>
    @if(count($p['kras']))
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                <th class="px-4 py-2 text-left">KRA Name / Objective</th>
                <th class="px-4 py-2 text-center">Target</th>
                <th class="px-4 py-2 text-center">Unit</th>
                <th class="px-4 py-2 text-center">Weight%</th>
                <th class="px-4 py-2 text-center">Frequency</th>
                <th class="px-4 py-2 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($p['kras'] as $kra)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <p class="font-semibold text-slate-800">{{ $kra->kra_name }}</p>
                    @if($kra->objective)<p class="text-xs text-slate-500">{{ $kra->objective }}</p>@endif
                    @if($kra->measure)<p class="text-xs text-slate-400"><i class="fas fa-ruler mr-1"></i>{{ $kra->measure }}</p>@endif
                </td>
                <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ number_format($kra->target, 0) }}</td>
                <td class="px-4 py-3 text-center text-slate-600">{{ $kra->unit }}</td>
                <td class="px-4 py-3 text-center">
                    <span class="bg-{{ $c }}-50 text-{{ $c }}-700 font-bold px-2 py-0.5 rounded text-xs">{{ $kra->weightage }}%</span>
                </td>
                <td class="px-4 py-3 text-center text-xs text-slate-500 capitalize">{{ $kra->review_frequency }}</td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <a href="{{ route('bsc.kras.edit', $kra) }}" class="text-amber-500 hover:text-amber-700 text-xs"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="{{ route('bsc.kras.destroy', $kra) }}" class="inline"
                              onsubmit="return confirm('Delete this KRA?')">
                            @csrf @method('DELETE')
                            <button class="text-red-400 hover:text-red-600 text-xs"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-{{ $c }}-50">
                <td colspan="3" class="px-4 py-2 text-xs text-{{ $c }}-700 font-semibold">Total Weightage</td>
                <td class="px-4 py-2 text-center text-xs font-bold text-{{ $c }}-700">{{ number_format($p['total_weightage'], 1) }}%</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    @else
    <div class="py-6 text-center text-slate-400 text-sm">
        <i class="fas fa-plus-circle mr-1"></i> No KRAs for this perspective yet.
        <a href="{{ route('bsc.kras.create', ['cycle' => $cycle, 'perspective' => $key]) }}" class="text-{{ $c }}-600 ml-1 font-semibold">Add one</a>
    </div>
    @endif
</div>
@endforeach

@php $colorMap = ['blue'=>'blue','green'=>'green','purple'=>'purple','orange'=>'amber']; @endphp
@endsection
