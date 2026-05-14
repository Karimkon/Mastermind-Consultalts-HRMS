@extends('layouts.app')
@section('title', 'Edit KRA')
@section('content')
<x-page-header title="Edit KRA">
    <a href="{{ route('bsc.cycles.show', $kra->cycle_id) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to Cycle</a>
</x-page-header>

<div class="max-w-2xl card p-6">
    <form method="POST" action="{{ route('bsc.kras.update', $kra) }}">
        @csrf @method('PUT')
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Perspective</label>
                <select name="perspective" class="form-input" required>
                    @php $perspectives = ['financial'=>'Financial (15%)','customer'=>'Customer (15%)','internal_process'=>'Internal Business Process (40%)','learning_growth'=>'Learning & Growth (30%)']; @endphp
                    @foreach($perspectives as $val => $label)
                    <option value="{{ $val }}" {{ old('perspective', $kra->perspective) == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">KRA Name</label>
                <input type="text" name="kra_name" value="{{ old('kra_name', $kra->kra_name) }}" class="form-input" required>
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Objective</label>
                <input type="text" name="objective" value="{{ old('objective', $kra->objective) }}" class="form-input">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Measure</label>
                <input type="text" name="measure" value="{{ old('measure', $kra->measure) }}" class="form-input">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Target Value</label>
                <input type="number" name="target" value="{{ old('target', $kra->target) }}" class="form-input" step="0.01" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Unit</label>
                <select name="unit" class="form-input">
                    @foreach(['%','Amount (ZAR)','Count','Score','Days','Hours','Rating'] as $u)
                    <option value="{{ $u }}" {{ old('unit', $kra->unit) == $u ? 'selected' : '' }}>{{ $u }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Weightage (%)</label>
                <input type="number" name="weightage" value="{{ old('weightage', $kra->weightage) }}" class="form-input" step="0.1" min="0" max="100" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Review Frequency</label>
                <select name="review_frequency" class="form-input">
                    @foreach(['monthly','quarterly','annual'] as $f)
                    <option value="{{ $f }}" {{ old('review_frequency', $kra->review_frequency) == $f ? 'selected' : '' }}>{{ ucfirst($f) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Sort Order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $kra->sort_order) }}" class="form-input" min="0">
            </div>
        </div>
        <div class="flex gap-3 pt-5 mt-5 border-t border-slate-100">
            <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
            <a href="{{ route('bsc.cycles.show', $kra->cycle_id) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
