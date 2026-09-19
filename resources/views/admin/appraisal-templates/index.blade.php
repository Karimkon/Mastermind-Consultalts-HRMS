@extends('layouts.app')
@section('title','Appraisal Templates')
@section('content')

<x-page-header title="Appraisal Weighting"
    subtitle="Set how each role's scorecard is weighted, and the KPIs it starts with">
    <a href="{{ route('appraisals.index') }}" class="btn-secondary"><i class="fas fa-clipboard-check mr-1"></i> Appraisals</a>
</x-page-header>

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

<div class="mb-5 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
    <i class="fas fa-circle-info mr-1"></i>
    A template says what share of a scorecard each perspective should carry — an Operations Officer
    runs 30 / 35 / 25 / 10, other roles differ. Supervisors apply a template and adjust from there.
    The four perspectives must total exactly 100%.
</div>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Template</th>
        <th class="table-head px-4 py-3 text-left">Suits</th>
        <th class="table-head px-4 py-3 text-center">Financials</th>
        <th class="table-head px-4 py-3 text-center">Customer</th>
        <th class="table-head px-4 py-3 text-center">Internal</th>
        <th class="table-head px-4 py-3 text-center">Learning</th>
        <th class="table-head px-4 py-3 text-center">KPIs</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
        <th class="table-head px-4 py-3"></th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($templates as $t)
        <tr class="table-row">
            <td class="px-6 py-3">
                <p class="text-sm font-medium text-slate-800">{{ $t->name }}</p>
                <p class="text-xs text-slate-400">{{ $t->description }}</p>
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $t->job_title ?? '—' }}</td>
            @foreach(['financial_weight','customer_weight','internal_process_weight','learning_growth_weight'] as $col)
            <td class="px-4 py-3 text-center text-sm font-medium text-slate-700">
                {{ rtrim(rtrim(number_format($t->$col, 2), '0'), '.') }}%
            </td>
            @endforeach
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ $t->kpis_count }}</td>
            <td class="px-4 py-3">
                <span class="badge-{{ $t->is_active ? 'green' : 'gray' }}">{{ $t->is_active ? 'Active' : 'Inactive' }}</span>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
                <a href="{{ route('admin.appraisal-templates.edit', $t) }}" class="text-blue-600 hover:underline text-xs">Edit</a>

                {{-- Next cycle usually wants this shape with a few targets moved.
                     Editing the original in place would rewrite the template that
                     finished appraisals were measured against. --}}
                <form method="POST" action="{{ route('admin.appraisal-templates.duplicate', $t) }}" class="inline ml-2">
                    @csrf
                    <button class="text-slate-500 hover:text-slate-800 text-xs"
                            title="Copy this template and its {{ $t->kpis_count }} KPI(s) to edit for the next round">
                        Duplicate
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.appraisal-templates.destroy', $t) }}" class="inline ml-2"
                      onsubmit="return confirm('Delete this template? Existing appraisals keep their KPIs.')">
                    @csrf @method('DELETE')
                    <button class="text-rose-500 hover:text-rose-700 text-xs">Delete</button>
                </form>
            </td>
        </tr>
        @empty
        <tr><td colspan="9" class="py-12 text-center">
            <i class="fas fa-sliders text-3xl text-slate-300 mb-3 block"></i>
            <p class="text-slate-500 text-sm">No templates yet — add one below.</p>
        </td></tr>
        @endforelse
    </tbody>
</x-data-table>

<div class="card p-5 mt-5">
    <h3 class="font-semibold text-slate-800 mb-3">New template</h3>
    <form method="POST" action="{{ route('admin.appraisal-templates.store') }}"
          class="grid grid-cols-1 md:grid-cols-12 gap-3">
        @csrf
        <div class="md:col-span-3">
            <label class="text-xs text-slate-500">Name</label>
            <input type="text" name="name" class="form-input text-sm" required placeholder="e.g. Operations Officer">
        </div>
        <div class="md:col-span-3">
            <label class="text-xs text-slate-500">Suits job title</label>
            <input type="text" name="job_title" class="form-input text-sm" placeholder="Operations Officer">
        </div>
        @foreach([
            'financial_weight' => ['Financials', 30],
            'customer_weight' => ['Customer', 35],
            'internal_process_weight' => ['Internal', 25],
            'learning_growth_weight' => ['Learning', 10],
        ] as $field => [$label, $default])
        <div class="md:col-span-1">
            <label class="text-xs text-slate-500">{{ $label }} %</label>
            <input type="number" step="0.01" min="0" max="100" name="{{ $field }}"
                   class="form-input text-sm" value="{{ $default }}" required>
        </div>
        @endforeach
        <div class="md:col-span-2 pt-5">
            <label class="inline-flex items-center gap-2 text-sm mr-3">
                <input type="checkbox" name="is_active" value="1" checked> Active
            </label>
            <button class="btn-primary px-3"><i class="fas fa-plus"></i></button>
        </div>
        <div class="md:col-span-12">
            <input type="text" name="description" class="form-input text-sm" placeholder="Short description (optional)">
        </div>
    </form>
    <p class="text-xs text-slate-500 mt-2">The four perspective weights must add up to exactly 100%.</p>
</div>
@endsection
